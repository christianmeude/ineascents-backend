<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\VerificationCode as VerificationCodeMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegisterVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_verify_then_login_flow_issues_no_token_until_verified()
    {
        Notification::fake();

        $register = $this->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
        ]);

        $register->assertStatus(201)->assertJsonMissingPath('access_token');

        $code = null;
        Notification::assertSentOnDemand(VerificationCodeMail::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });
        $this->assertNotNull($code);

        $blocked = $this->postJson('/api/login', [
            'email' => 'new@example.com',
            'password' => 'password123',
        ]);
        $blocked->assertStatus(422)->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');

        $verify = $this->postJson('/api/register/verify', [
            'email' => 'new@example.com',
            'code' => $code,
        ]);

        $verify->assertStatus(200)->assertJsonMissingPath('access_token');
        $this->assertNotNull(User::where('email', 'new@example.com')->first()->email_verified_at);

        $login = $this->postJson('/api/login', [
            'email' => 'new@example.com',
            'password' => 'password123',
        ]);

        $login->assertStatus(200)->assertJsonStructure(['access_token']);
    }

    public function test_register_verify_rejects_wrong_code()
    {
        Notification::fake();

        $this->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'wrong@example.com',
            'password' => 'password123',
        ])->assertStatus(201);

        $this->postJson('/api/register/verify', [
            'email' => 'wrong@example.com',
            'code' => '000000',
        ])->assertStatus(422);
    }

    public function test_register_verify_unknown_email_returns_none()
    {
        $this->postJson('/api/register/verify', [
            'email' => 'ghost@example.com',
            'code' => '482916',
        ])->assertStatus(404)->assertJsonPath('code', 'REGISTER_NONE');
    }

    public function test_register_resend_cools_down()
    {
        Notification::fake();

        $this->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'resend@example.com',
            'password' => 'password123',
        ])->assertStatus(201);

        $this->travel(61)->seconds();

        $this->postJson('/api/register/resend', [
            'email' => 'resend@example.com',
        ])->assertStatus(200)->assertJsonStructure(['message', 'code_expires_at']);

        $this->postJson('/api/register/resend', [
            'email' => 'resend@example.com',
        ])->assertStatus(429);
    }
}
