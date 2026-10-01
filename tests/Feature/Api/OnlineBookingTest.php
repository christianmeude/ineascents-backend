<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class OnlineBookingTest extends TestCase
{
    use RefreshDatabase;

    private function payload(Package $package): array
    {
        return [
            'package_id' => $package->id,
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'pax' => 2,
            'event_date' => '2026-12-15',
            'event_time' => '14:00:00',
            'venue_address' => '789 Event Place',
            'payment_method' => 'online',
            'consent_privacy_version' => \App\Http\Controllers\LegalController::VERSION,
        ];
    }

    public function test_online_booking_returns_checkout_url_on_paymongo_success(): void
    {
        $user = User::factory()->create();
        $package = Package::create([
            'name' => 'Test Package',
            'description' => 'Desc',
            'price' => 3500,
        ]);

        Http::fake([
            'api.paymongo.com/*' => Http::response([
                'data' => ['attributes' => ['checkout_url' => 'https://checkout.paymongo.test/session-abc']],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/bookings', $this->payload($package));

        $response->assertStatus(201)
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.checkout_url', 'https://checkout.paymongo.test/session-abc')
                ->etc()
            );

        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'checkout_url' => 'https://checkout.paymongo.test/session-abc',
        ]);
    }

    public function test_online_booking_is_rolled_back_when_paymongo_fails(): void
    {
        $user = User::factory()->create();
        $package = Package::create([
            'name' => 'Test Package',
            'description' => 'Desc',
            'price' => 3500,
        ]);

        Http::fake(['api.paymongo.com/*' => Http::response(['errors' => [['detail' => 'boom']]], 500)]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/bookings', $this->payload($package));

        $response->assertStatus(502)
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('message', 'Payment service is unreachable. No booking was made. Please try again.')
                ->etc()
            );

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_online_booking_is_rolled_back_when_paymongo_unreachable(): void
    {
        $user = User::factory()->create();
        $package = Package::create([
            'name' => 'Test Package',
            'description' => 'Desc',
            'price' => 3500,
        ]);

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 60');
        });

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/bookings', $this->payload($package));

        $response->assertStatus(502);
        $this->assertDatabaseCount('bookings', 0);

        // The date must not be squatted: a retry succeeds (cash path).
        $retry = $this->actingAs($user, 'sanctum')->postJson('/api/bookings', array_merge(
            $this->payload($package),
            ['payment_method' => 'cash']
        ));
        $retry->assertStatus(201);
        $this->assertSame(1, Booking::count());
    }

    public function test_total_price_is_derived_from_package(): void
    {
        $user = User::factory()->create();
        $package = Package::create([
            'name' => 'Test Package',
            'description' => 'Desc',
            'price' => 3500,
        ]);

        Http::fake([
            'api.paymongo.com/*' => Http::response([
                'data' => ['attributes' => ['checkout_url' => 'https://checkout.paymongo.test/s']],
            ], 200),
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/bookings', $this->payload($package));

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $payload['data']['attributes']['amount'] === 350000;
        });
    }
}
