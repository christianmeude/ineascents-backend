<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Feedback;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FeedbackManagementTest extends TestCase
{
    use RefreshDatabase;

    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    private function booking(User $user, string $status = 'Completed'): Booking
    {
        $package = Package::create([
            'name' => 'Test Package '.strtoupper(uniqid()),
            'description' => 'Desc',
            'price' => 100,
        ]);

        return Booking::create([
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

    public function test_admin_can_list_feedback_with_booking_status(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $booking = $this->booking($user);
        Feedback::create([
            'user_id' => $user->id,
            'booking_id' => $booking->id,
            'stars' => 5,
            'text' => 'Great service!',
        ]);

        $this->actingAs($this->admin)->get(route('admin.feedbacks.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Feedbacks/Index')
                ->has('feedbacks.data', 1)
                ->where('feedbacks.data.0.stars', 5)
                ->where('feedbacks.data.0.booking.booking_reference', $booking->booking_reference)
                ->where('feedbacks.data.0.booking.status', 'Completed')
            );
    }

    public function test_admin_can_filter_feedback_by_stars(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Feedback::create(['user_id' => $user->id, 'stars' => 5]);
        Feedback::create(['user_id' => $user->id, 'stars' => 2]);

        $this->actingAs($this->admin)->get(route('admin.feedbacks.index', ['stars' => 5]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Feedbacks/Index')
                ->has('feedbacks.data', 1)
                ->where('feedbacks.data.0.stars', 5)
            );
    }

    public function test_admin_can_view_feedback(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $feedback = Feedback::create(['user_id' => $user->id, 'stars' => 4, 'text' => 'Nice']);

        $this->actingAs($this->admin)->get(route('admin.feedbacks.show', $feedback))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Feedbacks/Show'));
    }

    public function test_bookings_index_includes_completed_status_and_linked_feedback(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $booking = $this->booking($user);
        Feedback::create([
            'user_id' => $user->id,
            'booking_id' => $booking->id,
            'stars' => 5,
            'text' => 'Great!',
        ]);

        $this->actingAs($this->admin)->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Bookings/Index')
                ->where('bookings.data.0.status', 'Completed')
                ->has('bookings.data.0.feedbacks', 1)
            );
    }

    public function test_non_admin_cannot_access(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $feedback = Feedback::create(['user_id' => $user->id, 'stars' => 5]);

        $this->actingAs($user)->get(route('admin.feedbacks.index'))->assertNotFound();
        $this->actingAs($user)->get(route('admin.feedbacks.show', $feedback))->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.feedbacks.index'))->assertRedirect('/admin/login');
    }
}
