<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\VerificationCodeException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VerificationCode as VerificationCodeMail;
use App\Services\VerificationCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public const PASSWORD_RESET_PURPOSE = 'password_reset';

    /**
     * Display the password reset code request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Send a numeric reset code. Always the same response —
     * existence of the address is never revealed.
     */
    public function store(Request $request, VerificationCodes $codes): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($request->email));
        $user = User::where('email', $email)->first();

        if ($user) {
            try {
                $issued = $codes->issue($user, self::PASSWORD_RESET_PURPOSE, $email);
            } catch (VerificationCodeException) {
                $issued = null;
            }

            if (isset($issued)) {
                Notification::route('mail', $email)
                    ->notify(new VerificationCodeMail($issued['code'], 'password reset'));
            }
        }

        return back()->with('status', 'If that email exists, a code was sent.');
    }
}
