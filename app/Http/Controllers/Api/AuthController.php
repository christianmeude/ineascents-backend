<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\VerificationCodeException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\VerificationCode as VerificationCodeMail;
use App\Services\VerificationCodes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OAT;

#[OAT\Schema(
    schema: 'User',
    type: 'object',
    properties: [
        new OAT\Property(property: 'id', type: 'integer', example: 1),
        new OAT\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OAT\Property(property: 'email', type: 'string', example: 'john@example.com'),
        new OAT\Property(property: 'is_admin', type: 'boolean', example: false),
    ]
)]
#[OAT\Schema(
    schema: 'AuthResponse',
    type: 'object',
    properties: [
        new OAT\Property(property: 'user', ref: '#/components/schemas/User'),
        new OAT\Property(property: 'access_token', type: 'string', example: '1|abcdef...'),
        new OAT\Property(property: 'token_type', type: 'string', example: 'Bearer'),
    ]
)]
class AuthController extends Controller
{
    public const REGISTER_PURPOSE = 'register';
    #[OAT\Post(
        path: '/api/register',
        summary: 'Register a new user',
        description: 'Creates a pending user and sends an email verification code. No session is issued until the email is verified.',
        tags: ['Auth']
    )]
    #[OAT\RequestBody(
        required: true,
        content: new OAT\JsonContent(
            required: ['name', 'email', 'password'],
            properties: [
                new OAT\Property(property: 'name', type: 'string', example: 'John Doe'),
                new OAT\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                new OAT\Property(property: 'password', type: 'string', format: 'password', example: 'secret'),
            ]
        )
    )]
    #[OAT\Response(
        response: 201,
        description: 'User created pending verification; code sent',
        content: new OAT\JsonContent(
            properties: [
                new OAT\Property(property: 'user', ref: '#/components/schemas/User'),
                new OAT\Property(property: 'message', type: 'string', example: 'Verify your email to finish registration.'),
                new OAT\Property(property: 'code_expires_at', type: 'string', format: 'date-time'),
            ],
            type: 'object'
        )
    )]
    #[OAT\Response(response: 422, description: 'Validation Error')]
    public function register(Request $request, VerificationCodes $codes)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        try {
            $issued = $codes->issue($user, self::REGISTER_PURPOSE, strtolower(trim($user->email)));
        } catch (VerificationCodeException $e) {
            return response()->json($e->toResponse(), $e->status);
        }

        Notification::route('mail', $user->email)
            ->notify(new VerificationCodeMail($issued['code'], 'email verification'));

        return response()->json([
            'user' => new UserResource($user),
            'message' => 'Verify your email to finish registration.',
            'code_expires_at' => $issued['record']->expires_at->toIso8601String(),
        ], 201);
    }

    #[OAT\Post(
        path: '/api/register/verify',
        summary: 'Verify registration email with code',
        description: 'Marks the pending user verified. No session is issued; log in afterwards.',
        tags: ['Auth']
    )]
    #[OAT\RequestBody(
        required: true,
        content: new OAT\JsonContent(
            required: ['email', 'code'],
            properties: [
                new OAT\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                new OAT\Property(property: 'code', type: 'string', example: '482916'),
            ]
        )
    )]
    #[OAT\Response(
        response: 200,
        description: 'Email verified; user may now log in',
        content: new OAT\JsonContent(ref: '#/components/schemas/User')
    )]
    #[OAT\Response(response: 404, description: 'No pending registration')]
    #[OAT\Response(response: 422, description: 'Wrong, expired, or locked code')]
    public function verifyRegistration(Request $request, VerificationCodes $codes)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'code' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return $this->noPendingRegistration();
        }

        $pending = $codes->pendingIncludingExpired($user, self::REGISTER_PURPOSE);

        if (! $pending) {
            return $this->noPendingRegistration();
        }

        try {
            $codes->verify($user, self::REGISTER_PURPOSE, $pending->identifier, $validated['code']);
        } catch (VerificationCodeException $e) {
            return response()->json($e->toResponse(), $e->status);
        }

        $user->email_verified_at = now();
        $user->save();

        return new UserResource($user);
    }

    #[OAT\Post(
        path: '/api/register/resend',
        summary: 'Re-send registration verification code',
        tags: ['Auth']
    )]
    #[OAT\RequestBody(
        required: true,
        content: new OAT\JsonContent(
            required: ['email'],
            properties: [
                new OAT\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
            ]
        )
    )]
    #[OAT\Response(
        response: 200,
        description: 'Fresh code sent to the registration address',
        content: new OAT\JsonContent(
            properties: [
                new OAT\Property(property: 'message', type: 'string', example: 'Code re-sent.'),
                new OAT\Property(property: 'code_expires_at', type: 'string', format: 'date-time'),
            ],
            type: 'object'
        )
    )]
    #[OAT\Response(response: 404, description: 'No pending registration')]
    #[OAT\Response(response: 429, description: 'Resend cooldown')]
    public function resendRegistrationCode(Request $request, VerificationCodes $codes)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return $this->noPendingRegistration();
        }

        $pending = $codes->pendingIncludingExpired($user, self::REGISTER_PURPOSE);

        if (! $pending) {
            return $this->noPendingRegistration();
        }

        try {
            $issued = $codes->resend($user, self::REGISTER_PURPOSE, $pending->identifier);
        } catch (VerificationCodeException $e) {
            return response()->json($e->toResponse(), $e->status);
        }

        Notification::route('mail', $pending->identifier)
            ->notify(new VerificationCodeMail($issued['code'], 'email verification'));

        return response()->json([
            'message' => 'Code re-sent.',
            'code_expires_at' => $issued['record']->expires_at->toIso8601String(),
        ]);
    }

    #[OAT\Post(
        path: '/api/login',
        summary: 'Login user and return token',
        tags: ['Auth']
    )]
    #[OAT\RequestBody(
        required: true,
        content: new OAT\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OAT\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                new OAT\Property(property: 'password', type: 'string', format: 'password', example: 'secret'),
            ]
        )
    )]
    #[OAT\Response(
        response: 200,
        description: 'User logged in successfully',
        content: new OAT\JsonContent(ref: '#/components/schemas/AuthResponse')
    )]
    #[OAT\Response(response: 422, description: 'Invalid credentials or Validation Error')]
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password) || $user->is_admin) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (is_null($user->email_verified_at)) {
            return response()->json([
                'message' => 'Verify your email before logging in. Check your inbox for the code.',
                'code' => 'EMAIL_NOT_VERIFIED',
            ], 422);
        }

        return $this->respondWithToken($user);
    }

    #[OAT\Get(
        path: '/api/user',
        summary: 'Get authenticated user',
        tags: ['Auth'],
        security: [['sanctum' => []]]
    )]
    #[OAT\Response(
        response: 200,
        description: 'User details',
        content: new OAT\JsonContent(ref: '#/components/schemas/User')
    )]
    #[OAT\Response(response: 401, description: 'Unauthenticated')]
    public function user(Request $request)
    {
        return $request->user();
    }

    private function noPendingRegistration()
    {
        return response()->json([
            'message' => 'No pending registration. Register first.',
            'code' => 'REGISTER_NONE',
        ], 404);
    }

    /**
     * Helper to format the authentication response.
     */
    private function respondWithToken(User $user)
    {
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => new \App\Http\Resources\UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
