<?php

namespace App\Actions;

use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A19: data-subject erasure — anonymize (never hard-delete, per owner
 * decision 2026-10-01) so booking/inquiry counts and calendar history
 * survive while contact PII is destroyed beyond re-identification.
 */
class EraseUserData
{
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user) {
            $oldEmail = strtolower($user->email);

            // Own bookings: scrub contact fields, keep the rows.
            foreach ($user->bookings()->get() as $booking) {
                $booking->update([
                    'customer_name' => 'Deleted Customer',
                    'customer_email' => "deleted-booking-{$booking->id}@deleted.local",
                    'customer_phone' => null,
                    'venue_address' => 'Deleted Venue',
                    'notes' => null,
                    'checkout_url' => null,
                ]);
            }

            // Inquiries matched by email (guests have no user_id).
            $inquiries = Inquiry::whereRaw('LOWER(email) = ?', [$oldEmail])->get();
            foreach ($inquiries as $inquiry) {
                $inquiry->update([
                    'name' => 'Deleted Inquirer',
                    'email' => "deleted-inquiry-{$inquiry->id}@deleted.local",
                    // phone is NOT NULL in schema: zeroed placeholder, not PII.
                    'phone' => '0000000000',
                    'message' => null,
                ]);
            }

            // Email-keyed leftovers: stale codes/tokens would block re-use
            // of the freed address or leak the link.
            $user->verificationCodes()->delete();
            DB::table('password_reset_tokens')
                ->whereRaw('LOWER(email) = ?', [$oldEmail])
                ->delete();

            // All sessions revoked, then the account itself anonymized.
            $user->tokens()->delete();
            $user->update([
                'name' => 'Deleted User',
                'email' => "deleted-{$user->id}@deleted.local",
                // Plain value: the model's 'hashed' cast hashes on write.
                'password' => Str::random(40),
                'email_verified_at' => null,
                'remember_token' => null,
            ]);
        });
    }
}
