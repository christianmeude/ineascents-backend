<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $email, string $password = 'password123'): string
    {
        return $this->postJson('/api/login', [
            'email' => $email,
            'password' => $password,
        ])->assertStatus(200)->json('access_token');
    }

    public function test_logout_revokes_current_token_only(): void
    {
        $user = User::factory()->create([
            'email' => 'logout@example.com',
            'password' => Hash::make('password123'),
        ]);

        $tokenA = $this->login('logout@example.com');
        $tokenB = $this->login('logout@example.com');

        $this->withToken($tokenA)->postJson('/api/logout')->assertStatus(200);

        Auth::forgetGuards();

        $this->withToken($tokenA)->getJson('/api/user')->assertStatus(401);
        $this->withToken($tokenB)->getJson('/api/user')->assertStatus(200);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_sixth_login_prunes_oldest_token(): void
    {
        $user = User::factory()->create([
            'email' => 'cap@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Seed 5 sessions directly (6 API logins would trip the login
        // throttle); the next API login is the 6th issuance and prunes.
        $first = $user->createToken('seed-1')->plainTextToken;
        for ($i = 0; $i < 4; $i++) {
            $user->createToken('seed-' . ($i + 2));
        }

        $this->login('cap@example.com');

        $this->assertSame(5, $user->tokens()->count());

        Auth::forgetGuards();

        $this->withToken($first)->getJson('/api/user')->assertStatus(401);
    }

    public function test_logout_requires_auth(): void
    {
        $this->postJson('/api/logout')->assertStatus(401);
    }
}
