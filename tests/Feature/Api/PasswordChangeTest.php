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

class PasswordChangeTest extends TestCase
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

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_request_sends_code_to_current_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/user/password/request', [], $this->authHeaders($user))
            ->assertStatus(200)
            ->assertJson(['message' => 'Code sent.']);

        Notification::assertSentOnDemand(VerificationCodeMail::class);
    }

    public function test_change_with_valid_current_and_code(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => Hash::make('oldpassword')]);

        $headers = $this->authHeaders($user);

        $this->postJson('/api/user/password/request', [], $headers)->assertStatus(200);

        $this->postJson('/api/user/password/change', [
            'current_password' => 'oldpassword',
            'code' => '482916',
            'password' => 'C0ncierge-Str0ng-77',
            'password_confirmation' => 'C0ncierge-Str0ng-77',
        ], $headers)
            ->assertStatus(200)
            ->assertJson(['code' => 'PASSWORD_CHANGED']);

        Auth::forgetGuards();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'C0ncierge-Str0ng-77'])
            ->assertStatus(200);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'oldpassword'])
            ->assertStatus(422);
    }

    public function test_success_revokes_other_tokens_only(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => Hash::make('oldpassword')]);

        $current = $this->authHeaders($user);
        $otherToken = $user->createToken('other')->plainTextToken;

        $this->postJson('/api/user/password/request', [], $current)->assertStatus(200);

        $this->postJson('/api/user/password/change', [
            'current_password' => 'oldpassword',
            'code' => '482916',
            'password' => 'C0ncierge-Str0ng-77',
            'password_confirmation' => 'C0ncierge-Str0ng-77',
        ], $current)->assertStatus(200);

        Auth::forgetGuards();

        $this->getJson('/api/user', ['Authorization' => 'Bearer '.$otherToken])
            ->assertStatus(401);

        Auth::forgetGuards();

        $this->getJson('/api/user', $current)->assertStatus(200);
    }

    public function test_wrong_current_password_rejected(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => Hash::make('oldpassword')]);

        $headers = $this->authHeaders($user);

        $this->postJson('/api/user/password/request', [], $headers)->assertStatus(200);

        $this->postJson('/api/user/password/change', [
            'current_password' => 'nottheright one',
            'code' => '482916',
            'password' => 'C0ncierge-Str0ng-77',
            'password_confirmation' => 'C0ncierge-Str0ng-77',
        ], $headers)
            ->assertStatus(422)
            ->assertJson(['code' => 'CURRENT_PASSWORD_WRONG']);

        $this->assertTrue(Hash::check('oldpassword', $user->fresh()->password));
    }

    public function test_wrong_code_rejected(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => Hash::make('oldpassword')]);

        $headers = $this->authHeaders($user);

        $this->postJson('/api/user/password/request', [], $headers)->assertStatus(200);

        $this->postJson('/api/user/password/change', [
            'current_password' => 'oldpassword',
            'code' => '000000',
            'password' => 'C0ncierge-Str0ng-77',
            'password_confirmation' => 'C0ncierge-Str0ng-77',
        ], $headers)
            ->assertStatus(422)
            ->assertJson(['code' => 'EMAIL_CODE_MISMATCH']);
    }

    public function test_expired_code_rejected(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => Hash::make('oldpassword')]);

        $headers = $this->authHeaders($user);

        $this->postJson('/api/user/password/request', [], $headers)->assertStatus(200);

        $this->travel(16)->minutes();

        $this->postJson('/api/user/password/change', [
            'current_password' => 'oldpassword',
            'code' => '482916',
            'password' => 'C0ncierge-Str0ng-77',
            'password_confirmation' => 'C0ncierge-Str0ng-77',
        ], $headers)
            ->assertStatus(422)
            ->assertJson(['code' => 'EMAIL_CODE_EXPIRED']);
    }

    public function test_change_without_request_reports_none(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpassword')]);

        $this->postJson('/api/user/password/change', [
            'current_password' => 'oldpassword',
            'code' => '482916',
            'password' => 'C0ncierge-Str0ng-77',
            'password_confirmation' => 'C0ncierge-Str0ng-77',
        ], $this->authHeaders($user))
            ->assertStatus(404)
            ->assertJson(['code' => 'PASSWORD_CHANGE_NONE']);
    }

    public function test_weak_password_rejected_with_field_error(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => Hash::make('oldpassword')]);

        $headers = $this->authHeaders($user);

        $this->postJson('/api/user/password/request', [], $headers)->assertStatus(200);

        $this->postJson('/api/user/password/change', [
            'current_password' => 'oldpassword',
            'code' => '482916',
            'password' => 'short',
            'password_confirmation' => 'short',
        ], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_request_within_cooldown_rejected(): void
    {
        $user = User::factory()->create();

        $headers = $this->authHeaders($user);

        $this->postJson('/api/user/password/request', [], $headers)->assertStatus(200);

        Auth::forgetGuards();

        $this->postJson('/api/user/password/request', [], $this->authHeaders($user))
            ->assertStatus(429)
            ->assertJson(['code' => 'EMAIL_CODE_RESEND_TOO_SOON']);
    }

    public function test_five_wrong_codes_lock(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpassword')]);

        $headers = $this->authHeaders($user);

        $this->postJson('/api/user/password/request', [], $headers)->assertStatus(200);

        $payload = [
            'current_password' => 'oldpassword',
            'code' => '000000',
            'password' => 'C0ncierge-Str0ng-77',
            'password_confirmation' => 'C0ncierge-Str0ng-77',
        ];

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/user/password/change', $payload, $headers)
                ->assertStatus(422)
                ->assertJson(['code' => 'EMAIL_CODE_MISMATCH']);
        }

        $this->postJson('/api/user/password/change', $payload, $headers)
            ->assertStatus(422)
            ->assertJson(['code' => 'EMAIL_CODE_LOCKED']);
    }

    public function test_unauthenticated_request_rejected(): void
    {
        $this->postJson('/api/user/password/request', [])->assertStatus(401);
        $this->postJson('/api/user/password/change', [])->assertStatus(401);
    }
}
