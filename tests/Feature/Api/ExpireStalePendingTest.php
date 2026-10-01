<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireStalePendingTest extends TestCase
{
    use RefreshDatabase;

    private function stalePending(User $user, Package $package, string $date): Booking
    {
        $booking = Booking::create([
            'booking_reference' => 'BOOKING-STALE-' . $date,
            'user_id' => $user->id,
            'package_id' => $package->id,
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'pax' => 2,
            'event_date' => $date,
            'venue_address' => '123 Test',
            'payment_method' => 'online',
        ]);
        // Backdate past the hold window via the query builder: created_at is
        // not mass-assignable, so create()/update() would silently drop it.
        \Illuminate\Support\Facades\DB::table('bookings')->where('id', $booking->id)->update([
            'created_at' => now()->subMinutes(16),
            'updated_at' => now()->subMinutes(16),
        ]);

        return $booking->fresh();
    }

    public function test_availability_never_shows_stale_pending_as_booked(): void
    {
        $user = User::factory()->create();
        $package = Package::create(['name' => 'P', 'description' => 'D', 'price' => 100]);
        $this->stalePending($user, $package, '2026-10-10');

        $response = $this->getJson('/api/availability?month=10&year=2026');

        $response->assertStatus(200)
            ->assertJsonFragment(['date' => '2026-10-10', 'status' => 'Available']);

        $this->assertSame(BookingStatus::Cancelled->value, Booking::first()->status->value);
    }

    public function test_stale_pending_does_not_block_same_date_booking(): void
    {
        $user = User::factory()->create();
        $package = Package::create(['name' => 'P', 'description' => 'D', 'price' => 100]);
        $this->stalePending($user, $package, '2026-10-10');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/bookings', [
            'package_id' => $package->id,
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'pax' => 2,
            'event_date' => '2026-10-10',
            'venue_address' => '789 Place',
            'payment_method' => 'cash',
            'consent_privacy_version' => \App\Http\Controllers\LegalController::VERSION,
        ]);

        $response->assertStatus(201);
    }

    public function test_fresh_pending_still_blocks_same_date(): void
    {
        $user = User::factory()->create();
        $package = Package::create(['name' => 'P', 'description' => 'D', 'price' => 100]);
        Booking::create([
            'booking_reference' => 'BOOKING-FRESH',
            'user_id' => $user->id,
            'package_id' => $package->id,
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'pax' => 2,
            'event_date' => '2026-10-10',
            'venue_address' => '123 Test',
            'payment_method' => 'online',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/bookings', [
            'package_id' => $package->id,
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'pax' => 2,
            'event_date' => '2026-10-10',
            'venue_address' => '789 Place',
            'payment_method' => 'cash',
            'consent_privacy_version' => \App\Http\Controllers\LegalController::VERSION,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('event_date');
    }

    public function test_bookings_list_sweeps_stale_pending(): void
    {
        $user = User::factory()->create();
        $package = Package::create(['name' => 'P', 'description' => 'D', 'price' => 100]);
        $this->stalePending($user, $package, '2026-10-10');

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/bookings');

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => BookingStatus::Cancelled->value]);
    }
}
