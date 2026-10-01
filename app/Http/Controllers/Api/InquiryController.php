<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LegalController;
use App\Http\Resources\InquiryResource;
use App\Models\Inquiry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use OpenApi\Attributes as OAT;

class InquiryController extends Controller
{
    #[OAT\Post(
        path: '/api/inquiries',
        summary: 'Submit a new inquiry',
        description: 'Public lead capture for the landing page. One row per submission; email is not unique.',
        tags: ['Inquiries']
    )]
    #[OAT\RequestBody(
        required: true,
        content: new OAT\JsonContent(
            required: ['name', 'email', 'phone', 'consent_privacy_version'],
            properties: [
                new OAT\Property(property: 'name', type: 'string'),
                new OAT\Property(property: 'email', type: 'string'),
                new OAT\Property(property: 'phone', type: 'string'),
                new OAT\Property(property: 'event_date', type: 'string', format: 'date', nullable: true),
                new OAT\Property(property: 'message', type: 'string', nullable: true),
                new OAT\Property(property: 'consent_privacy_version', type: 'string', example: LegalController::VERSION),
            ]
        )
    )]
    #[OAT\Response(
        response: 201,
        description: 'Inquiry created successfully',
        content: new OAT\JsonContent(
            properties: [
                new OAT\Property(
                    property: 'data',
                    ref: '#/components/schemas/Inquiry'
                )
            ],
            type: 'object'
        )
    )]
    public function store(Request $request)
    {
        // Honeypot: bots fill `website`; drop silently with a fake success.
        if ($request->filled('website')) {
            return response()->json(['message' => 'Inquiry received.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:30',
            'event_date' => ['nullable', 'date', 'after_or_equal:'.Carbon::now('Asia/Manila')->toDateString()],
            'message' => 'nullable|string|max:2000',
            // A18: consent must pin the exact current policy text.
            'consent_privacy_version' => ['required', 'string', Rule::in([LegalController::VERSION])],
        ]);

        // Server-set timestamp: provable when, not client-claimed.
        $validated['consented_at'] = now();

        // Blank Details means absent, like a blank event date.
        $validated['message'] = $request->filled('message') ? $validated['message'] : null;

        $inquiry = Inquiry::create($validated);

        app(\App\Services\AdminNotifier::class)->alert(
            'inquiry.submitted',
            'New inquiry',
            "{$inquiry->name} requested an event consultation.",
            route('admin.inquiries.show', $inquiry->id),
        );

        return new InquiryResource($inquiry);
    }
}
