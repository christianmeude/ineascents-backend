<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\VerificationCode as VerificationCodeMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use App\Services\VerificationCodes;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
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

    public function test_name_updates_inline(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson('/api/user', ['name' => 'New Name'], $this->authHeaders($user));

        $response->assertStatus(200)
            ->assertValidResponse(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('email_pending', null);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_email_request_sends_code_to_new_address(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@example.com']);

        $response = $this->putJson(
            '/api/user',
            ['name' => 'Old Name', 'email' => 'new@example.com'],
            $this->authHeaders($user)
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'old@example.com')
            ->assertJsonPath('email_pending', 'new@example.com');

        Notification::assertSentOnDemand(VerificationCodeMail::class);
        $this->assertDatabaseHas('verification_codes', [
            'user_id' => $user->id,
            'purpose' => 'email_change',
            'identifier' => 'new@example.com',
        ]);
    }

    public function test_old_email_still_logs_in_while_unverified(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->putJson('/api/user', ['email' => 'new@example.com'], $this->authHeaders($user));

        $login = $this->postJson('/api/login', [
            'email' => 'old@example.com',
            'password' => 'password123',
        ]);

        $login->assertStatus(200)->assertJsonMissingPath('errors');

        $newLogin = $this->postJson('/api/login', [
            'email' => 'new@example.com',
            'password' => 'password123',
        ]);

        $newLogin->assertStatus(422);
    }

    public function test_verify_with_correct_code_swaps_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->putJson('/api/user', ['email' => 'new@example.com'], $this->authHeaders($user));

        $response = $this->postJson('/api/user/email/verify', ['code' => '482916'], $this->authHeaders($user));

        $response->assertStatus(200)
            ->assertValidResponse(200)
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new@example.com']);
        $this->assertDatabaseMissing('verification_codes', ['user_id' => $user->id]);
    }

    public function test_verify_with_wrong_code_reports_mismatch(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->putJson('/api/user', ['email' => 'new@example.com'], $this->authHeaders($user));

        $response = $this->postJson('/api/user/email/verify', ['code' => '000000'], $this->authHeaders($user));

        $response->assertStatus(422)
            ->assertJson(['code' => 'EMAIL_CODE_MISMATCH'])
            ->assertJsonPath('attempts_left', 4);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'old@example.com']);
    }

    public function test_five_wrong_codes_lock_and_void(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->putJson('/api/user', ['email' => 'new@example.com'], $this->authHeaders($user));

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/user/email/verify', ['code' => '000000'], $this->authHeaders($user))
                ->assertStatus(422)
                ->assertJson(['code' => 'EMAIL_CODE_MISMATCH']);
        }

        $this->postJson('/api/user/email/verify', ['code' => '000000'], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJson(['code' => 'EMAIL_CODE_LOCKED']);

        $this->postJson('/api/user/email/verify', ['code' => '000000'], $this->authHeaders($user))
            ->assertStatus(404)
            ->assertJson(['code' => 'EMAIL_CHANGE_NONE']);
    }

    public function test_expired_code_rejected(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->putJson('/api/user', ['email' => 'new@example.com'], $this->authHeaders($user));

        $this->travel(16)->minutes();

        $this->postJson('/api/user/email/verify', ['code' => '482916'], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJson(['code' => 'EMAIL_CODE_EXPIRED']);
    }

    public function test_resend_within_cooldown_rejected(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->putJson('/api/user', ['email' => 'new@example.com'], $this->authHeaders($user));

        $this->postJson('/api/user/email/resend', [], $this->authHeaders($user))
            ->assertStatus(429)
            ->assertJson(['code' => 'EMAIL_CODE_RESEND_TOO_SOON']);
    }

    public function test_resend_after_cooldown_sends_fresh_code(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->putJson('/api/user', ['email' => 'new@example.com'], $this->authHeaders($user));

        $this->travel(61)->seconds();

        $this->postJson('/api/user/email/resend', [], $this->authHeaders($user))
            ->assertStatus(200)
            ->assertJson(['message' => 'Code re-sent.']);

        Notification::assertSentOnDemandTimes(VerificationCodeMail::class, 2);

        $this->postJson('/api/user/email/verify', ['code' => '482916'], $this->authHeaders($user))
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'new@example.com');
    }

    public function test_taken_email_rejected(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->putJson('/api/user', ['email' => 'taken@example.com'], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJson(['code' => 'EMAIL_TAKEN']);
    }

    public function test_reserved_pending_email_rejected_for_other_user(): void
    {
        Notification::fake();

        $first = User::factory()->create(['email' => 'first@example.com']);
        $second = User::factory()->create(['email' => 'second@example.com']);

        $this->putJson('/api/user', ['email' => 'wanted@example.com'], $this->authHeaders($first));

        Auth::forgetGuards();

        $this->putJson('/api/user', ['email' => 'wanted@example.com'], $this->authHeaders($second))
            ->assertStatus(422)
            ->assertJson(['code' => 'EMAIL_TAKEN']);
    }

    public function test_same_email_sends_no_code(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'same@example.com', 'name' => 'Old']);

        $this->putJson(
            '/api/user',
            ['name' => 'New', 'email' => 'same@example.com'],
            $this->authHeaders($user)
        )->assertStatus(200)->assertJsonPath('email_pending', null);

        Notification::assertNothingSent();
    }

    public function test_unauthenticated_profile_update_rejected(): void
    {
        $this->putJson('/api/user', ['name' => 'Nope'])->assertStatus(401);
    }

    public function test_invalid_email_format_rejected_with_field_error(): void
    {
        $user = User::factory()->create();

        $this->putJson('/api/user', ['email' => 'not-an-email'], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
