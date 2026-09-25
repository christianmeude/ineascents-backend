<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\VerificationCodeException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Notifications\VerificationCode as VerificationCodeMail;
use App\Services\VerificationCodes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use OpenApi\Attributes as OAT;

class ProfileController extends Controller
{
    public const EMAIL_CHANGE_PURPOSE = 'email_change';

    #[OAT\Put(
        path: '/api/user',
        summary: 'Update profile name, request email change',
        description: 'Name saves inline. A new email issues a verification code to that address; the login email swaps only after verification.',
        security: [['sanctum' => []]],
        tags: ['Profile']
    )]
    #[OAT\RequestBody(
        required: true,
        content: new OAT\JsonContent(
            properties: [
                new OAT\Property(property: 'name', type: 'string', example: 'Jane Doe'),
                new OAT\Property(property: 'email', type: 'string', format: 'email', example: 'jane@example.com'),
            ]
        )
    )]
    #[OAT\Response(
        response: 200,
        description: 'Profile updated; email_pending set when a code was sent',
        content: new OAT\JsonContent(
            properties: [
                new OAT\Property(property: 'data', ref: '#/components/schemas/User'),
                new OAT\Property(property: 'email_pending', type: 'string', nullable: true, example: 'jane@example.com'),
                new OAT\Property(property: 'code_expires_at', type: 'string', format: 'date-time', nullable: true),
            ],
            type: 'object'
        )
    )]
    #[OAT\Response(response: 422, description: 'Validation Error or email taken')]
    #[OAT\Response(response: 429, description: 'Code resend cooldown')]
    public function update(Request $request, VerificationCodes $codes)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
        ]);

        if (array_key_exists('name', $validated)) {
            $user->name = $validated['name'];
        }

        $pendingEmail = null;
        $expiresAt = null;

        $newEmail = isset($validated['email']) ? strtolower(trim($validated['email'])) : null;

        if ($newEmail && $newEmail !== strtolower($user->email)) {
            if ($codes->isIdentifierReserved(self::EMAIL_CHANGE_PURPOSE, $newEmail, $user)) {
                return response()->json([
                    'message' => 'That email is already in use.',
                    'code' => 'EMAIL_TAKEN',
                ], 422);
            }

            try {
                $issued = $codes->issue($user, self::EMAIL_CHANGE_PURPOSE, $newEmail);
            } catch (VerificationCodeException $e) {
                $user->save();

                return response()->json($e->toResponse(), $e->status);
            }

            Notification::route('mail', $newEmail)
                ->notify(new VerificationCodeMail($issued['code'], 'email change'));

            $pendingEmail = $newEmail;
            $expiresAt = $issued['record']->expires_at->toIso8601String();
        }

        $user->save();

        return (new UserResource($user))->additional([
            'email_pending' => $pendingEmail,
            'code_expires_at' => $expiresAt,
        ]);
    }

    #[OAT\Post(
        path: '/api/user/email/verify',
        summary: 'Verify email change with code',
        security: [['sanctum' => []]],
        tags: ['Profile']
    )]
    #[OAT\RequestBody(
        required: true,
        content: new OAT\JsonContent(
            required: ['code'],
            properties: [
                new OAT\Property(property: 'code', type: 'string', example: '482916'),
            ]
        )
    )]
    #[OAT\Response(
        response: 200,
        description: 'Email swapped to the verified address',
        content: new OAT\JsonContent(
            properties: [
                new OAT\Property(property: 'data', ref: '#/components/schemas/User'),
            ],
            type: 'object'
        )
    )]
    #[OAT\Response(response: 404, description: 'No pending email change')]
    #[OAT\Response(response: 422, description: 'Wrong, expired, or locked code')]
    public function verifyEmail(Request $request, VerificationCodes $codes)
    {
        $user = $request->user();

        $validated = $request->validate(['code' => 'required|string']);

        $pending = $codes->pendingIncludingExpired($user, self::EMAIL_CHANGE_PURPOSE);

        if (! $pending) {
            return $this->noPending();
        }

        try {
            $codes->verify($user, self::EMAIL_CHANGE_PURPOSE, $pending->identifier, $validated['code']);
        } catch (VerificationCodeException $e) {
            return response()->json($e->toResponse(), $e->status);
        }

        $user->email = $pending->identifier;
        $user->email_verified_at = null;
        $user->save();

        return new UserResource($user);
    }

    #[OAT\Post(
        path: '/api/user/email/resend',
        summary: 'Re-send email change code',
        security: [['sanctum' => []]],
        tags: ['Profile']
    )]
    #[OAT\Response(
        response: 200,
        description: 'Fresh code sent to the pending address',
        content: new OAT\JsonContent(
            properties: [
                new OAT\Property(property: 'message', type: 'string', example: 'Code re-sent.'),
                new OAT\Property(property: 'code_expires_at', type: 'string', format: 'date-time'),
            ],
            type: 'object'
        )
    )]
    #[OAT\Response(response: 404, description: 'No pending email change')]
    #[OAT\Response(response: 429, description: 'Resend cooldown')]
    public function resendEmailCode(Request $request, VerificationCodes $codes)
    {
        $user = $request->user();

        $pending = $codes->pendingIncludingExpired($user, self::EMAIL_CHANGE_PURPOSE);

        if (! $pending) {
            return $this->noPending();
        }

        try {
            $issued = $codes->resend($user, self::EMAIL_CHANGE_PURPOSE, $pending->identifier);
        } catch (VerificationCodeException $e) {
            return response()->json($e->toResponse(), $e->status);
        }

        Notification::route('mail', $pending->identifier)
            ->notify(new VerificationCodeMail($issued['code'], 'email change'));

        return response()->json([
            'message' => 'Code re-sent.',
            'code_expires_at' => $issued['record']->expires_at->toIso8601String(),
        ]);
    }

    private function noPending()
    {
        return response()->json([
            'message' => 'No pending email change. Request a new code first.',
            'code' => 'EMAIL_CHANGE_NONE',
        ], 404);
    }

    private function emailTaken(string $email, User $user): bool
    {
        if (User::where('email', $email)->where('id', '!=', $user->id)->exists()) {
            return true;
        }

        return VerificationCode::where('purpose', self::EMAIL_CHANGE_PURPOSE)
            ->where('identifier', $email)
            ->where('user_id', '!=', $user->id)
            ->where('expires_at', '>', now())
            ->exists();
    }
}
