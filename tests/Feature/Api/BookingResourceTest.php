<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class BookingResourceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every key the OpenAPI Booking schema promises must be emitted by
     * BookingResource. checkout_url was silently dropped, so credit_card
     * bookings never delivered the PayMongo link to the client.
     */
    public function test_booking_payload_contains_all_spec_promised_keys(): void
    {
        $user = User::factory()->create();
        $package = Package::create([
            'name' => 'Test Package',
            'description' => 'Desc',
            'price' => 100,
        ]);

        Booking::create([
            'booking_reference' => 'BOOKING-CHECKOUT',
            'user_id' => $user->id,
            'package_id' => $package->id,
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'pax' => 2,
            'event_date' => '2026-10-10',
            'venue_address' => '123 Test',
            'payment_method' => 'online',
            'checkout_url' => 'https://checkout.paymongo.test/session-123',
        ]);

        $expectedKeys = [
            'id',
            'booking_reference',
            'user_id',
            'customer_name',
            'customer_email',
            'customer_phone',
            'pax',
            'event_date',
            'event_time',
            'venue_address',
            'payment_method',
            'status',
            'checkout_url',
            'consent_privacy_version',
            'consented_at',
            'package',
            'scents',
        ];

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/bookings');

        $response->assertStatus(200)
            ->assertJson(fn (AssertableJson $json) => $json->has('data', 1)
                ->has('data.0', fn (AssertableJson $json) => $json
                    ->where('checkout_url', 'https://checkout.paymongo.test/session-123')
                    ->hasAll($expectedKeys)
                )
            );
    }
}
