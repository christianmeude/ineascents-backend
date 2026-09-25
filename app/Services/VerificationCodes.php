<?php

namespace App\Services;

use App\Exceptions\VerificationCodeException;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\Hash;

/**
 * Shared numeric-code issue/verify/resend store.
 * Rule (owner-settled): 6 digits, 15-min expiry, 60-sec resend
 * cooldown, 5 wrong attempts voids the code.
 */
class VerificationCodes
{
    public const EXPIRY_MINUTES = 15;

    public const COOLDOWN_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    /**
     * Issue a fresh code, replacing any pending one for this slot.
     * Returns the plain code (only the hash is stored) and its expiry.
     *
     * @throws VerificationCodeException on resend cooldown
     */
    public function issue(?User $user, string $purpose, string $identifier): array
    {
        $this->pruneExpired();

        $existing = $this->findUnexpired($user, $purpose, $identifier);

        $this->assertCooldown($existing);

        $existing?->delete();

        VerificationCode::where('user_id', $user?->id)
            ->where('purpose', $purpose)
            ->where('identifier', '!=', $identifier)
            ->delete();

        $code = $this->generateCode();

        $record = VerificationCode::create([
            'user_id' => $user?->id,
            'purpose' => $purpose,
            'identifier' => $identifier,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            'attempts' => 0,
            'last_sent_at' => now(),
        ]);

        return ['code' => $code, 'record' => $record->fresh()];
    }

    /**
     * Re-send a pending code with a fresh value and expiry.
     *
     * @throws VerificationCodeException
     */
    public function resend(?User $user, string $purpose, string $identifier): array
    {
        $record = VerificationCode::where('purpose', $purpose)
            ->where('identifier', $identifier)
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->first();

        if (! $record) {
            throw new VerificationCodeException(
                'EMAIL_CHANGE_NONE',
                'No pending code. Request a new one first.',
                404,
            );
        }

        $this->assertCooldown($record);

        $code = $this->generateCode();

        $record->update([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            'attempts' => 0,
            'last_sent_at' => now(),
        ]);

        return ['code' => $code, 'record' => $record->fresh()];
    }

    /**
     * Is this identifier reserved by a different owner — a live user
     * email or another user's unexpired pending code?
     */
    public function isIdentifierReserved(string $purpose, string $identifier, ?User $excludingUser): bool
    {
        if ($purpose === 'email_change'
            && User::where('email', $identifier)
                ->when($excludingUser, fn ($q) => $q->where('id', '!=', $excludingUser->id))
                ->exists()) {
            return true;
        }

        return VerificationCode::where('purpose', $purpose)
            ->where('identifier', $identifier)
            ->when($excludingUser, fn ($q) => $q->where('user_id', '!=', $excludingUser->id))
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Verify a code. Consumes the record on success.
     *
     * @throws VerificationCodeException
     */
    public function verify(?User $user, string $purpose, string $identifier, string $code): void
    {
        $record = VerificationCode::where('purpose', $purpose)
            ->where('identifier', $identifier)
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->first();

        if (! $record) {
            $this->pruneExpired();

            throw new VerificationCodeException(
                'EMAIL_CHANGE_NONE',
                'No pending code. Request a new one first.',
                404,
            );
        }

        if ($record->isExpired()) {
            $record->delete();

            throw new VerificationCodeException(
                'EMAIL_CODE_EXPIRED',
                'That code expired. Request a new one.',
                422,
            );
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            $record->delete();

            throw new VerificationCodeException(
                'EMAIL_CODE_LOCKED',
                'Too many wrong attempts. Request a new code.',
                422,
            );
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            if ($record->attempts >= self::MAX_ATTEMPTS) {
                $record->delete();

                throw new VerificationCodeException(
                    'EMAIL_CODE_LOCKED',
                    'Too many wrong attempts. Request a new code.',
                    422,
                );
            }

            $left = self::MAX_ATTEMPTS - $record->attempts;

            throw new VerificationCodeException(
                'EMAIL_CODE_MISMATCH',
                "That code doesn't match. {$left} tries left.",
                422,
                ['attempts_left' => $left],
            );
        }

        $record->delete();
    }

    public function pendingIncludingExpired(User $user, string $purpose): ?VerificationCode
    {
        return VerificationCode::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->first();
    }

    private function findUnexpired(?User $user, string $purpose, string $identifier): ?VerificationCode
    {
        return VerificationCode::where('purpose', $purpose)
            ->where('identifier', $identifier)
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->where('expires_at', '>', now())
            ->first();
    }

    private function assertCooldown(?VerificationCode $record): void
    {
        if ($record && $record->last_sent_at
            && $record->last_sent_at->diffInSeconds(now()) < self::COOLDOWN_SECONDS) {
            throw new VerificationCodeException(
                'EMAIL_CODE_RESEND_TOO_SOON',
                'Please wait a minute before requesting a new code.',
                429,
            );
        }
    }

    private function pruneExpired(): void
    {
        VerificationCode::where('expires_at', '<=', now())->delete();
    }

    protected function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }
}
