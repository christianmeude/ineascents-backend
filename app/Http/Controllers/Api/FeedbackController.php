<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\FeedbackResource;
use App\Models\Booking;
use App\Models\Feedback;
use Illuminate\Http\Request;

use OpenApi\Attributes as OAT;

#[OAT\Tag(
    name: 'Feedback',
    description: 'API Endpoints for User Feedback'
)]
class FeedbackController extends Controller
{
    #[OAT\Post(
        path: '/api/feedback',
        summary: 'Submit feedback',
        description: 'Stores one feedback row per submission for the authenticated user.',
        security: [['sanctum' => []]],
        tags: ['Feedback']
    )]
    #[OAT\RequestBody(
        required: true,
        content: new OAT\JsonContent(
            required: ['stars'],
            properties: [
                new OAT\Property(property: 'stars', type: 'integer', minimum: 1, maximum: 5),
                new OAT\Property(property: 'text', type: 'string', nullable: true, maxLength: 500),
                new OAT\Property(property: 'booking_id', type: 'integer', nullable: true),
            ]
        )
    )]
    #[OAT\Response(
        response: 201,
        description: 'Feedback created successfully',
        content: new OAT\JsonContent(
            properties: [
                new OAT\Property(
                    property: 'data',
                    ref: '#/components/schemas/Feedback'
                )
            ],
            type: 'object'
        )
    )]
    #[OAT\Response(response: 422, description: 'Validation failed')]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'stars' => 'required|integer|min:1|max:5',
            'text' => 'nullable|string|max:500',
            'booking_id' => 'nullable|exists:bookings,id',
        ]);

        if (! empty($validated['booking_id'])) {
            $booking = Booking::find($validated['booking_id']);

            if ((int) $booking->user_id !== (int) $request->user()->id
                || $booking->status !== BookingStatus::Completed) {
                return response()->json([
                    'message' => 'The selected booking is invalid.',
                    'errors' => [
                        'booking_id' => ['The selected booking must be your completed booking.'],
                    ],
                ], 422);
            }
        }

        // Blank text means absent.
        $validated['text'] = $request->filled('text') ? $validated['text'] : null;

        $feedback = Feedback::create([
            'user_id' => $request->user()->id,
            'booking_id' => $validated['booking_id'] ?? null,
            'stars' => $validated['stars'],
            'text' => $validated['text'],
        ]);

        return (new FeedbackResource($feedback))->response()->setStatusCode(201);
    }
}
