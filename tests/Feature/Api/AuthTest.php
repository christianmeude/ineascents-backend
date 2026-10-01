<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register()
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'C0ncierge-Str0ng-77',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(201)
            ->assertValidRequest()
            ->assertValidResponse(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'message',
                'code_expires_at',
            ])
            ->assertJsonMissingPath('access_token');

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'email_verified_at' => null,
        ]);

        $this->assertDatabaseHas('verification_codes', [
            'purpose' => 'register',
            'identifier' => 'test@example.com',
        ]);
    }

    public function test_user_cannot_register_with_invalid_data()
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'password' => 'short',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $payload = [
            'email' => 'login@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/login', $payload);

        $response->assertStatus(200)
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'access_token',
                'token_type',
            ]);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'login2@example.com',
            'password' => Hash::make('password123'),
        ]);

        $payload = [
            'email' => 'login2@example.com',
            'password' => 'wrongpassword',
        ];

        $response = $this->postJson('/api/login', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_unverified_user_cannot_login()
    {
        User::factory()->unverified()->create([
            'email' => 'pending@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'pending@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'EMAIL_NOT_VERIFIED')
            ->assertJsonMissingPath('access_token');
    }

    public function test_admin_cannot_login_through_customer_api()
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'is_admin' => true,
        ]);

        $payload = [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/login', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonMissingPath('access_token');
    }

    public function test_user_cannot_register_with_short_or_breached_password()
    {
        $this->postJson('/api/register', [
            'name' => 'Weak User',
            'email' => 'weak@example.com',
            'password' => 'password123',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        // Deterministic breach rejection: fake the HIBP k-anonymity range
        // response to contain this password's suffix (fails closed in test,
        // live API verified separately during development).
        $breached = 'newpassword123'; // 14 chars but in breach corpora
        $sha = strtoupper(sha1($breached));
        \Illuminate\Support\Facades\Http::fake([
            'api.pwnedpasswords.com/*' => \Illuminate\Support\Facades\Http::response(substr($sha, 5) . ":42\n"),
        ]);

        $this->postJson('/api/register', [
            'name' => 'Breached User',
            'email' => 'breached@example.com',
            'password' => $breached,
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }
}
