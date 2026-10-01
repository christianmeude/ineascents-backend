<?php

namespace App\Actions;

use App\Http\Resources\BookingResource;
use App\Http\Resources\InquiryResource;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * A19: data-subject access — one JSON bundle of everything held about
 * the requester (profile, own bookings, inquiries matched by email).
 * Read-only; scoped strictly to the authenticated user.
 */
class ExportUserData
{
    public function execute(User $user, Request $request): array
    {
        $bookings = $user->bookings()->with(['package', 'scents'])->get();

        $inquiries = Inquiry::whereRaw('LOWER(email) = ?', [strtolower($user->email)])
            ->orderBy('id')
            ->get();

        // Resources use whenLoaded only; request passed through, never stored.
        return [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
            ],
            'bookings' => BookingResource::collection($bookings)->toArray($request),
            'inquiries' => InquiryResource::collection($inquiries)->toArray($request),
        ];
    }
}
