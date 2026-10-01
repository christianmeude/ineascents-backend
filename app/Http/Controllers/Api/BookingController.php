<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Controllers\LegalController;
use App\Models\Booking;
use App\Models\Package;
use App\Rules\PaxInTiers;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use OpenApi\Attributes as OAT;

#[OAT\Tag(
    name: 'Bookings',
    description: 'API Endpoints for User Bookings'
)]
class BookingController extends Controller
{
    // Trigger CI 8
    #[OAT\Get(
        path: '/api/bookings',
        summary: "Get user's bookings",
        description: 'Returns a list of all bookings for the authenticated user.',
        security: [['sanctum' => []]],
        tags: ['Bookings']
    )]
    #[OAT\Response(
        response: 200,
        description: 'Successful operation',
        content: new OAT\JsonContent(
            properties: [
                new OAT\Property(
                    property: 'data',
                    type: 'array',
                    items: new OAT\Items(ref: '#/components/schemas/Booking')
                )
            ],
            type: 'object'
        )
    )]
    public function index(Request $request)
    {
        $user = $request->user();
        \App\Models\Booking::expireStalePending();
        $bookings = $user->bookings()->with(['package', 'scents'])->get();

        return \App\Http\Resources\BookingResource::collection($bookings);
    }

    #[OAT\Post(
        path: '/api/bookings',
        summary: 'Create a new booking',
        description: 'Creates a new booking for the authenticated user',
        security: [['sanctum' => []]],
        tags: ['Bookings']
    )]
    #[OAT\RequestBody(
        required: true,
        content: new OAT\JsonContent(
            required: ['package_id', 'customer_name', 'customer_email', 'pax', 'event_date', 'venue_address', 'payment_method', 'consent_privacy_version'],
            properties: [
                new OAT\Property(property: 'package_id', type: 'integer'),
                new OAT\Property(property: 'customer_name', type: 'string'),
                new OAT\Property(property: 'customer_email', type: 'string'),
                new OAT\Property(property: 'customer_phone', type: 'string'),
                new OAT\Property(property: 'pax', type: 'integer'),
                new OAT\Property(property: 'event_date', type: 'string', format: 'date'),
                new OAT\Property(property: 'event_time', type: 'string', format: 'time'),
                new OAT\Property(property: 'venue_address', type: 'string'),
                new OAT\Property(property: 'payment_method', type: 'string', enum: ['online', 'cash']),
                new OAT\Property(property: 'scent_ids', type: 'array', items: new OAT\Items(type: 'integer')),
                new OAT\Property(property: 'consent_privacy_version', type: 'string', example: LegalController::VERSION),
            ]
        )
    )]
    #[OAT\Response(
        response: 201,
        description: 'Booking created successfully',
        content: new OAT\JsonContent(
            properties: [
                new OAT\Property(
                    property: 'data',
                    ref: '#/components/schemas/Booking'
                )
            ],
            type: 'object'
        )
    )]
    public function store(Request $request, \App\Actions\CreateBookingWithCheckout $checkout)
    {
        $request->validate(['package_id' => 'required|exists:packages,id']);
        $package = Package::findOrFail($request->input('package_id'));

        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:255',
            'pax' => ['required', new PaxInTiers($package)],
            'event_date' => 'required|date',
            'event_time' => 'nullable|date_format:H:i:s',
            'venue_address' => 'required|string|max:255',
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'scent_ids' => 'nullable|array',
            'scent_ids.*' => 'exists:scents,id',
            // A18: consent must pin the exact current policy text.
            'consent_privacy_version' => ['required', 'string', Rule::in([LegalController::VERSION])],
        ]);

        // Server-set timestamp: provable when, not client-claimed.
        $validated['consented_at'] = now();

        try {
            $booking = $checkout->execute($validated, $request->user());
        } catch (\App\Exceptions\PaymentLinkFailedException $e) {
            // Never leak provider internals to the client.
            return response()->json(['message' => $e->getMessage()], 502);
        }

        app(\App\Services\AdminNotifier::class)->alert(
            'booking.created',
            'New booking',
            "{$booking->customer_name} booked {$booking->event_date} ({$booking->booking_reference}).",
            route('admin.bookings.index', ['search' => $booking->booking_reference]),
        );

        return new \App\Http\Resources\BookingResource($booking->load(['package', 'scents']));
    }
}
