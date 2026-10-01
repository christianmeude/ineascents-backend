<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\VerificationCodeException;
use App\Http\Controllers\Controller;
use App\Notifications\VerificationCode as VerificationCodeMail;
use App\Services\VerificationCodes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes as OAT;

class PasswordController extends Controller
{
    public const PASSWORD_CHANGE_PURPOSE = 'password_change';

    private function slot(Request $request): string
    {
        return 'user:'.$request->user()->id;
    }

    #[OAT\Post(
        path: '/api/user/password/request',
        summary: 'Send password-change code to current email',
        security: [['sanctum' => []]],
        tags: ['Profile']
    )]
    #[OAT\Response(
        response: 200,
        description: 'Code sent',
        content: new OAT\JsonContent(
            properties: [
                new OAT\Property(property: 'message', type: 'string', example: 'Code sent.'),
                new OAT\Property(property: 'code_expires_at', type: 'string', format: 'date-time'),
            ],
            type: 'object'
        )
    )]
    #[OAT\Response(response: 429, description: 'Resend cooldown')]
    public function request(Request $request, VerificationCodes $codes)
    {
        $user = $request->user();

        try {
            $issued = $codes->issue($user, self::PASSWORD_CHANGE_PURPOSE, $this->slot($request));
        } catch (VerificationCodeException $e) {
            return response()->json($e->toResponse(), $e->status);
        }

        Notification::route('mail', $user->email)
            ->notify(new VerificationCodeMail($issued['code'], 'password change'));

        return response()->json([
            'message' => 'Code sent.',
            'code_expires_at' => $issued['record']->expires_at->toIso8601String(),
        ]);
    }

    #[OAT\Post(
        path: '/api/user/password/change',
        summary: 'Change password with current password + code',
        security: [['sanctum' => []]],
        tags: ['Profile']
    )]
    #[OAT\RequestBody(
        required: true,
        content: new OAT\JsonContent(
            required: ['current_password', 'code', 'password', 'password_confirmation'],
            properties: [
                new OAT\Property(property: 'current_password', type: 'string', format: 'password'),
                new OAT\Property(property: 'code', type: 'string', example: '482916'),
                new OAT\Property(property: 'password', type: 'string', format: 'password'),
                new OAT\Property(property: 'password_confirmation', type: 'string', format: 'password'),
            ]
        )
    )]
    #[OAT\Response(
        response: 200,
        description: 'Password changed; other tokens revoked',
        content: new OAT\JsonContent(
            properties: [
                new OAT\Property(property: 'message', type: 'string', example: 'Password changed.'),
                new OAT\Property(property: 'code', type: 'string', example: 'PASSWORD_CHANGED'),
            ],
            type: 'object'
        )
    )]
    #[OAT\Response(response: 422, description: 'Wrong current password, bad code, or validation error')]
    public function change(Request $request, VerificationCodes $codes)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'code' => 'required|string',
            'password' => ['required', 'string', Password::min(12)->uncompromised(), 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
                'code' => 'CURRENT_PASSWORD_WRONG',
            ], 422);
        }

        try {
            $codes->verify($user, self::PASSWORD_CHANGE_PURPOSE, $this->slot($request), $validated['code']);
        } catch (VerificationCodeException $e) {
            return response()->json($e->toResponse(), $e->status);
        }

        $user->password = $validated['password'];
        $user->save();

        $currentTokenId = $request->user()->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json([
            'message' => 'Password changed.',
            'code' => 'PASSWORD_CHANGED',
        ]);
    }
}
