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
            'password' => 'password123',
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
}
