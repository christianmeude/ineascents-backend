<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\VerificationCode as VerificationCodeMail;
use App\Services\VerificationCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(VerificationCodes::class, fn () => new class('482916') extends VerificationCodes
        {
            public function __construct(private string $fixed) {}

            protected function generateCode(): string
            {
                return $this->fixed;
            }
        });
    }

    public function test_unknown_email_returns_identical_success(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertStatus(200)
            ->assertValidResponse(200)
            ->assertJson(['code' => 'PASSWORD_RESET_SENT']);

        Notification::assertNothingSent();
    }

    public function test_known_email_sends_code(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/forgot-password', ['email' => 'jane@example.com'])
            ->assertStatus(200)
            ->assertJson(['code' => 'PASSWORD_RESET_SENT']);

        Notification::assertSentOnDemand(VerificationCodeMail::class);
    }

    public function test_admin_email_gets_no_code_but_same_success(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'admin@example.com', 'is_admin' => true]);

        $this->postJson('/api/forgot-password', ['email' => 'admin@example.com'])
            ->assertStatus(200)
            ->assertJson(['code' => 'PASSWORD_RESET_SENT']);

        Notification::assertNothingSent();
    }

    public function test_reset_with_valid_code_revokes_all_tokens(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => Hash::make('oldpassword'),
        ]);

        $oldToken = $user->createToken('old')->plainTextToken;

        $this->postJson('/api/forgot-password', ['email' => 'jane@example.com'])
            ->assertStatus(200);

        $this->postJson('/api/reset-password', [
            'email' => 'jane@example.com',
            'code' => '482916',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
            ->assertStatus(200)
            ->assertValidResponse(200)
            ->assertJson(['code' => 'PASSWORD_RESET_DONE']);

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));

        Auth::forgetGuards();

        $this->getJson('/api/user', ['Authorization' => 'Bearer '.$oldToken])
            ->assertStatus(401);

        $this->postJson('/api/login', ['email' => 'jane@example.com', 'password' => 'newpassword123'])
            ->assertStatus(200);
    }

    public function test_reset_with_wrong_code_rejected(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/forgot-password', ['email' => 'jane@example.com'])
            ->assertStatus(200);

        $this->postJson('/api/reset-password', [
            'email' => 'jane@example.com',
            'code' => '000000',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
            ->assertStatus(422)
            ->assertJson(['code' => 'EMAIL_CODE_MISMATCH']);
    }

    public function test_reset_with_expired_code_rejected(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/forgot-password', ['email' => 'jane@example.com'])
            ->assertStatus(200);

        $this->travel(16)->minutes();

        $this->postJson('/api/reset-password', [
            'email' => 'jane@example.com',
            'code' => '482916',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
            ->assertStatus(422)
            ->assertJson(['code' => 'EMAIL_CODE_EXPIRED']);
    }

    public function test_reset_without_request_reports_none(): void
    {
        $this->postJson('/api/reset-password', [
            'email' => 'jane@example.com',
            'code' => '482916',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
            ->assertStatus(404)
            ->assertJson(['code' => 'PASSWORD_RESET_NONE']);
    }

    public function test_five_wrong_codes_lock(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/forgot-password', ['email' => 'jane@example.com'])
            ->assertStatus(200);

        $payload = [
            'email' => 'jane@example.com',
            'code' => '000000',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ];

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/reset-password', $payload)
                ->assertStatus(422)
                ->assertJson(['code' => 'EMAIL_CODE_MISMATCH']);
        }

        $this->postJson('/api/reset-password', $payload)
            ->assertStatus(422)
            ->assertJson(['code' => 'EMAIL_CODE_LOCKED']);
    }
}
