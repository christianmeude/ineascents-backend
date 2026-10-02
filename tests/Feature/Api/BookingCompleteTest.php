<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCompleteTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(User $user, string $status, string $eventDate): Booking
    {
        $package = Package::create([
            'name' => 'Test Package '.strtoupper(uniqid()),
            'description' => 'Desc',
            'price' => 100,
        ]);

        return Booking::create([
            'booking_reference' => 'BOOKING-'.strtoupper(uniqid()),
            'user_id' => $user->id,
            'package_id' => $package->id,
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'pax' => 2,
            'event_date' => $eventDate,
            'venue_address' => '123 Test',
            'payment_method' => 'cash',
            'status' => $status,
        ]);
    }

    public function test_owner_can_complete_past_confirmed_booking(): void
    {
        $user = User::factory()->create();
        $past = Carbon::now('Asia/Manila')->subDay()->toDateString();
        $booking = $this->makeBooking($user, 'Confirmed', $past);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/complete");

        $response->assertStatus(200);
        $this->assertEquals('Completed', $booking->refresh()->status->value);
    }

    public function test_admin_can_complete_others_past_confirmed_booking(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $past = Carbon::now('Asia/Manila')->subDay()->toDateString();
        $booking = $this->makeBooking($owner, 'Confirmed', $past);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/complete");

        $response->assertStatus(200);
        $this->assertEquals('Completed', $booking->refresh()->status->value);
    }

    public function test_complete_is_idempotent(): void
    {
        $user = User::factory()->create();
        $past = Carbon::now('Asia/Manila')->subDay()->toDateString();
        $booking = $this->makeBooking($user, 'Confirmed', $past);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/complete")
            ->assertStatus(200);

        $replay = $this->actingAs($user, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/complete");

        $replay->assertStatus(200)
            ->assertJsonPath('data.status', 'Completed');
        $this->assertEquals('Completed', $booking->refresh()->status->value);
    }

    public function test_complete_rejects_same_day_event_date(): void
    {
        $user = User::factory()->create();
        $today = Carbon::now('Asia/Manila')->toDateString();
        $booking = $this->makeBooking($user, 'Confirmed', $today);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/complete")
            ->assertStatus(422);
        $this->assertEquals('Confirmed', $booking->refresh()->status->value);
    }

    public function test_complete_rejects_pending_and_cancelled(): void
    {
        $user = User::factory()->create();
        $past = Carbon::now('Asia/Manila')->subDay()->toDateString();

        $pending = $this->makeBooking($user, 'Pending', $past);
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/bookings/{$pending->id}/complete")
            ->assertStatus(422);

        $cancelled = $this->makeBooking($user, 'Cancelled', $past);
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/bookings/{$cancelled->id}/complete")
            ->assertStatus(422);
    }

    public function test_complete_rejects_non_owner_and_future_date(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $past = Carbon::now('Asia/Manila')->subDay()->toDateString();
        $future = Carbon::now('Asia/Manila')->addDay()->toDateString();

        $booking = $this->makeBooking($owner, 'Confirmed', $past);
        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/complete")
            ->assertStatus(403);

        $upcoming = $this->makeBooking($owner, 'Confirmed', $future);
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/bookings/{$upcoming->id}/complete")
            ->assertStatus(422);
        $this->assertEquals('Confirmed', $upcoming->refresh()->status->value);
    }

    public function test_availability_treats_completed_as_booked(): void
    {
        $user = User::factory()->create();
        $date = '2024-08-10';
        $this->makeBooking($user, 'Completed', $date);

        $this->getJson('/api/availability?month=8&year=2024')
            ->assertStatus(200)
            ->assertJsonFragment(['date' => $date, 'status' => 'Booked']);
    }
}
