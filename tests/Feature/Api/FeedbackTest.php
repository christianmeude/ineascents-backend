<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Feedback;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(User $user, string $status): Booking
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
            'event_date' => '2024-08-10',
            'venue_address' => '123 Test',
            'payment_method' => 'cash',
            'status' => $status,
        ]);
    }

    public function test_guest_cannot_submit_feedback(): void
    {
        $this->postJson('/api/feedback', ['stars' => 5])->assertStatus(401);
    }

    public function test_stars_is_required_between_1_and_5(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/feedback', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stars']);

        foreach ([0, 6] as $stars) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/feedback', ['stars' => $stars])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['stars']);
        }

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/feedback', ['stars' => 'five'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stars']);
    }

    public function test_text_is_optional_and_capped_at_500_chars(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/feedback', ['stars' => 4])
            ->assertStatus(201)
            ->assertJsonPath('data.stars', 4)
            ->assertJsonPath('data.text', null);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/feedback', ['stars' => 5, 'text' => str_repeat('a', 501)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['text']);
    }

    public function test_can_link_own_completed_booking(): void
    {
        $user = User::factory()->create();
        $booking = $this->makeBooking($user, 'Completed');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/feedback', [
                'stars' => 5,
                'text' => 'Great service!',
                'booking_id' => $booking->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.booking_id', $booking->id)
            ->assertJsonPath('data.stars', 5);

        $this->assertDatabaseHas('feedbacks', [
            'user_id' => $user->id,
            'booking_id' => $booking->id,
            'stars' => 5,
        ]);
    }

    public function test_rejects_other_users_booking(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $booking = $this->makeBooking($owner, 'Completed');

        $this->actingAs($stranger, 'sanctum')
            ->postJson('/api/feedback', ['stars' => 5, 'booking_id' => $booking->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['booking_id']);

        $this->assertDatabaseMissing('feedbacks', ['booking_id' => $booking->id]);
    }

    public function test_rejects_non_completed_booking(): void
    {
        $user = User::factory()->create();
        $booking = $this->makeBooking($user, 'Confirmed');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/feedback', ['stars' => 5, 'booking_id' => $booking->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['booking_id']);

        $this->assertDatabaseMissing('feedbacks', ['booking_id' => $booking->id]);
    }

    public function test_rejects_unknown_booking(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/feedback', ['stars' => 5, 'booking_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['booking_id']);
    }

    public function test_duplicate_submissions_allowed(): void
    {
        $user = User::factory()->create();
        $payload = ['stars' => 3, 'text' => 'Okay'];

        $this->actingAs($user, 'sanctum')->postJson('/api/feedback', $payload)->assertStatus(201);
        $this->actingAs($user, 'sanctum')->postJson('/api/feedback', $payload)->assertStatus(201);

        $this->assertEquals(2, Feedback::where('user_id', $user->id)->count());
    }
}
