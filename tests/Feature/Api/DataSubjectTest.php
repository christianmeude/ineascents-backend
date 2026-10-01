<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DataSubjectTest extends TestCase
{
    use RefreshDatabase;

    private function seedFor(User $user, string $email): void
    {
        $package = Package::create(['name' => 'P-' . $user->id, 'description' => 'D', 'price' => 100]);

        Booking::create([
            'booking_reference' => 'BOOKING-DSAR-' . $user->id,
            'user_id' => $user->id,
            'package_id' => $package->id,
            'customer_name' => 'DSAR Person',
            'customer_email' => $email,
            'pax' => 2,
            'event_date' => '2026-12-10',
            'venue_address' => '123 Private St',
            'payment_method' => 'cash',
            'consent_privacy_version' => \App\Http\Controllers\LegalController::VERSION,
            'consented_at' => now(),
        ]);

        Inquiry::create([
            'name' => 'DSAR Person',
            'email' => $email,
            'phone' => '+639171234567',
            'message' => 'Quote please',
            'consent_privacy_version' => \App\Http\Controllers\LegalController::VERSION,
            'consented_at' => now(),
        ]);
    }

    public function test_export_returns_only_requester_rows(): void
    {
        $me = User::factory()->create(['email' => 'me@example.com']);
        $other = User::factory()->create(['email' => 'other@example.com']);
        $this->seedFor($me, 'me@example.com');
        $this->seedFor($other, 'other@example.com');

        $export = $this->actingAs($me, 'sanctum')->getJson('/api/user/export')->assertStatus(200);

        $export->assertJsonPath('user.email', 'me@example.com')
            ->assertJsonCount(1, 'bookings')
            ->assertJsonPath('bookings.0.customer_email', 'me@example.com')
            ->assertJsonCount(1, 'inquiries')
            ->assertJsonPath('inquiries.0.email', 'me@example.com');
    }

    public function test_export_requires_auth(): void
    {
        $this->getJson('/api/user/export')->assertStatus(401);
        $this->deleteJson('/api/user')->assertStatus(401);
    }

    public function test_admin_cannot_use_data_rights_endpoints(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/user/export')->assertStatus(403);
        $this->actingAs($admin, 'sanctum')->deleteJson('/api/user')->assertStatus(403);
    }

    public function test_delete_anonymizes_and_revokes_but_keeps_counts(): void
    {
        $me = User::factory()->create([
            'email' => 'gone@example.com',
            'password' => Hash::make('password123'),
        ]);
        $this->seedFor($me, 'gone@example.com');

        $token = $this->postJson('/api/login', [
            'email' => 'gone@example.com', 'password' => 'password123',
        ])->assertStatus(200)->json('access_token');

        $this->withToken($token)->deleteJson('/api/user')->assertStatus(200);

        // Sessions revoked.
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertStatus(401);

        // Counts preserved, PII destroyed.
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('inquiries', 1);
        $this->assertDatabaseMissing('bookings', ['customer_email' => 'gone@example.com']);
        $this->assertDatabaseMissing('inquiries', ['email' => 'gone@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'gone@example.com']);
        $this->assertSame(0, $me->tokens()->count());

        // Freed address can register again.
        $this->postJson('/api/register', [
            'name' => 'Reborn',
            'email' => 'gone@example.com',
            'password' => 'C0ncierge-Str0ng-77',
        ])->assertStatus(201);
    }
}
