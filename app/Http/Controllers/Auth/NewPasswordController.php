<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\VerificationCodeException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\VerificationCodes;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->email,
        ]);
    }

    /**
     * Handle an incoming new password request via numeric code.
     *
     * @throws ValidationException
     */
    public function store(Request $request, VerificationCodes $codes)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $email = strtolower(trim($request->email));
        $user = User::where('email', $email)->first();

        try {
            $codes->verify($user, PasswordResetLinkController::PASSWORD_RESET_PURPOSE, $email, $request->code);
        } catch (VerificationCodeException $e) {
            throw ValidationException::withMessages([
                $e->status === 404 ? 'email' : 'code' => [$e->getMessage()],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        $user->tokens()->delete();

        event(new PasswordReset($user));

        return redirect()->route('login');
    }
}
