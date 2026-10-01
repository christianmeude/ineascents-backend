<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Scent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_their_bookings(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $package = Package::create([
            'name' => 'Test Package',
            'description' => 'Desc',
            'price' => 100,
        ]);

        $booking1 = Booking::create([
            'booking_reference' => 'BOOKING-11111',
            'user_id' => $user->id,
            'package_id' => $package->id,
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'pax' => 2,
            'event_date' => '2026-10-10',
            'venue_address' => '123 Test',
            'payment_method' => 'online',
        ]);

        $booking2 = Booking::create([
            'booking_reference' => 'BOOKING-22222',
            'user_id' => $otherUser->id,
            'package_id' => $package->id,
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'pax' => 2,
            'event_date' => '2026-11-10',
            'venue_address' => '456 Test',
            'payment_method' => 'cash',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/bookings');

        $response->assertStatus(200)
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->assertJson(fn (AssertableJson $json) => $json->has('data', 1)
                ->has('data.0', fn (AssertableJson $json) => $json->where('id', $booking1->id)
                    ->where('user_id', $user->id)
                    ->has('scents')
                    ->has('package')
                    ->etc()
                )
            );
    }

    public function test_unauthenticated_user_cannot_get_bookings(): void
    {
        $response = $this->getJson('/api/bookings');
        $response->assertStatus(401)
            ->assertValidRequest();
    }

    public function test_authenticated_user_can_create_booking_with_scents(): void
    {
        $user = User::factory()->create();
        $package = Package::create([
            'name' => 'Test Package',
            'description' => 'Desc',
            'price' => 100,
        ]);
        $scent1 = Scent::create(['name' => 'Scent 1', 'description' => 'Desc']);
        $scent2 = Scent::create(['name' => 'Scent 2', 'description' => 'Desc']);

        $bookingData = [
            'package_id' => $package->id,
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'pax' => 4,
            'event_date' => '2026-12-15',
            'event_time' => '10:00:00',
            'venue_address' => '789 Event Place',
            'payment_method' => 'cash',
            'scent_ids' => [$scent1->id, $scent2->id],
            'consent_privacy_version' => \App\Http\Controllers\LegalController::VERSION,
        ];

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/bookings', $bookingData);

        $response->assertStatus(201)
            ->assertValidRequest()
            ->assertValidResponse(201)
            ->assertJson(fn (AssertableJson $json) => $json->where('data.user_id', $user->id)
                ->where('data.customer_name', 'Jane Doe')
                ->where('data.payment_method', 'cash')
                ->has('data.scents', 2)
                ->etc()
            );

        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'customer_name' => 'Jane Doe',
            'payment_method' => 'cash',
        ]);

        $booking = Booking::first();
        $this->assertCount(2, $booking->scents);
    }

    public function test_unauthenticated_user_cannot_create_booking(): void
    {
        $response = $this->postJson('/api/bookings', []);
        $response->assertStatus(401);
    }

    public function test_booking_without_consent_is_rejected(): void
    {
        $user = User::factory()->create();
        $package = Package::create([
            'name' => 'Test Package',
            'description' => 'Desc',
            'price' => 100,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/bookings', [
            'package_id' => $package->id,
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'pax' => 4,
            'event_date' => '2026-12-15',
            'venue_address' => '789 Event Place',
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['consent_privacy_version']);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_with_stale_consent_and_timestamp_persist(): void
    {
        $user = User::factory()->create();
        $package = Package::create([
            'name' => 'Test Package',
            'description' => 'Desc',
            'price' => 100,
        ]);

        $base = [
            'package_id' => $package->id,
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'pax' => 4,
            'event_date' => '2026-12-15',
            'venue_address' => '789 Event Place',
            'payment_method' => 'cash',
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/bookings', array_merge($base, ['consent_privacy_version' => '2000-01-01']))
            ->assertStatus(422)->assertJsonValidationErrors(['consent_privacy_version']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/bookings', array_merge($base, [
                'consent_privacy_version' => \App\Http\Controllers\LegalController::VERSION,
            ]))->assertStatus(201);

        $this->assertDatabaseHas('bookings', [
            'customer_email' => 'jane@example.com',
            'consent_privacy_version' => \App\Http\Controllers\LegalController::VERSION,
        ]);
        $this->assertNotNull(Booking::first()->consented_at);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('retiredPaymentMethods')]
    public function test_retired_payment_methods_are_rejected(string $method): void
    {
        $user = User::factory()->create();
        $package = Package::create([
            'name' => 'Test Package',
            'description' => 'Desc',
            'price' => 100,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/bookings', [
            'package_id' => $package->id,
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'pax' => 4,
            'event_date' => '2026-12-15',
            'venue_address' => '789 Event Place',
            'payment_method' => $method,
            'consent_privacy_version' => \App\Http\Controllers\LegalController::VERSION,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('bookings', 0);
    }

    public static function retiredPaymentMethods(): array
    {
        return [
            'bank_transfer' => ['bank_transfer'],
            'gcash' => ['gcash'],
            'maya' => ['maya'],
            'credit_card' => ['credit_card'],
        ];
    }
}
