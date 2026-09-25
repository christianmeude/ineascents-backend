<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\VerificationCodeException;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VerificationCode as VerificationCodeMail;
use App\Services\VerificationCodes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use OpenApi\Attributes as OAT;

class PasswordResetController extends Controller
{
    #[OAT\Post(
        path: '/api/forgot-password',
        summary: 'Send password-reset code',
        description: 'Always the same response; address existence is never revealed.',
        tags: ['Auth']
    )]
    #[OAT\RequestBody(
        required: true,
        content: new OAT\JsonContent(
            required: ['email'],
            properties: [
                new OAT\Property(property: 'email', type: 'string', format: 'email', example: 'jane@example.com'),
            ]
        )
    )]
    #[OAT\Response(
        response: 200,
        description: 'Code sent if the address exists',
        content: new OAT\JsonContent(
            properties: [
                new OAT\Property(property: 'message', type: 'string', example: 'If that email exists, a code was sent.'),
                new OAT\Property(property: 'code', type: 'string', example: 'PASSWORD_RESET_SENT'),
            ],
            type: 'object'
        )
    )]
    public function request(Request $request, VerificationCodes $codes)
    {
        $request->validate(['email' => 'required|email']);

        $email = strtolower(trim($request->email));
        $user = User::where('email', $email)->where('is_admin', false)->first();

        if ($user) {
            try {
                $issued = $codes->issue($user, PasswordResetLinkController::PASSWORD_RESET_PURPOSE, $email);
            } catch (VerificationCodeException) {
                $issued = null;
            }

            if (isset($issued)) {
                Notification::route('mail', $email)
                    ->notify(new VerificationCodeMail($issued['code'], 'password reset'));
            }
        }

        return response()->json([
            'message' => 'If that email exists, a code was sent.',
            'code' => 'PASSWORD_RESET_SENT',
        ]);
    }

    #[OAT\Post(
        path: '/api/reset-password',
        summary: 'Reset password with code',
        tags: ['Auth']
    )]
    #[OAT\RequestBody(
        required: true,
        content: new OAT\JsonContent(
            required: ['email', 'code', 'password', 'password_confirmation'],
            properties: [
                new OAT\Property(property: 'email', type: 'string', format: 'email', example: 'jane@example.com'),
                new OAT\Property(property: 'code', type: 'string', example: '482916'),
                new OAT\Property(property: 'password', type: 'string', format: 'password'),
                new OAT\Property(property: 'password_confirmation', type: 'string', format: 'password'),
            ]
        )
    )]
    #[OAT\Response(
        response: 200,
        description: 'Password reset; all tokens revoked',
        content: new OAT\JsonContent(
            properties: [
                new OAT\Property(property: 'message', type: 'string', example: 'Password reset.'),
                new OAT\Property(property: 'code', type: 'string', example: 'PASSWORD_RESET_DONE'),
            ],
            type: 'object'
        )
    )]
    #[OAT\Response(response: 404, description: 'No pending reset')]
    #[OAT\Response(response: 422, description: 'Wrong, expired, or locked code')]
    public function reset(Request $request, VerificationCodes $codes)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::where('email', $email)->where('is_admin', false)->first();

        try {
            $codes->verify($user, PasswordResetLinkController::PASSWORD_RESET_PURPOSE, $email, $validated['code']);
        } catch (VerificationCodeException $e) {
            return response()->json($e->toResponse(), $e->status);
        }

        $user->password = $validated['password'];
        $user->save();

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset.',
            'code' => 'PASSWORD_RESET_DONE',
        ]);
    }
}
