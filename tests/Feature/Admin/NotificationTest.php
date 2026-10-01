<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'whsec_test_123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->package = Package::create([
            'name' => 'Signature',
            'description' => 'Test',
            'price' => 1000,
            'pax_options' => [50],
            'pax_prices' => [50 => 4499],
        ]);
    }

    public function test_guest_cannot_reach_notification_endpoints()
    {
        $this->getJson('/admin/notifications')->assertRedirect('/admin/login');
    }

    public function test_non_admin_gets_not_found()
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->getJson('/admin/notifications')->assertNotFound();
    }

    public function test_inquiry_submit_notifies_admins()
    {
        $this->postJson('/api/inquiries', [
            'name' => 'Lead Person',
            'email' => 'lead@example.com',
            'phone' => '09171234567',
            'consent_privacy_version' => \App\Http\Controllers\LegalController::VERSION,
        ])->assertCreated();

        $this->assertDatabaseHas('notifications', ['data->type' => 'inquiry.submitted']);
        $this->assertSame(1, $this->admin->fresh()->unreadNotifications()->count());
    }

    public function test_honeypot_submit_does_not_notify()
    {
        $this->postJson('/api/inquiries', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'phone' => '09171234567',
            'website' => 'http://spam.example',
        ])->assertOk();

        $this->assertDatabaseMissing('notifications', ['data->type' => 'inquiry.submitted']);
    }

    public function test_cash_booking_create_notifies_admins()
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user, 'sanctum')->postJson('/api/bookings', [
            'package_id' => $this->package->id,
            'customer_name' => 'Jane Smith',
            'customer_email' => 'jane@example.com',
            'pax' => 50,
            'event_date' => '2026-12-20',
            'venue_address' => '123 Test St',
            'payment_method' => 'cash',
            'consent_privacy_version' => \App\Http\Controllers\LegalController::VERSION,
        ])->assertCreated();

        $this->assertDatabaseHas('notifications', ['data->type' => 'booking.created']);
    }

    public function test_verified_paid_webhook_notifies_on_confirm()
    {
        config(['services.paymongo.webhook_secret' => $this->secret]);
        $booking = Booking::create([
            'booking_reference' => 'INEA-NOTIF-1',
            'customer_name' => 'Jane Smith',
            'customer_email' => 'jane@example.com',
            'package_id' => $this->package->id,
            'pax' => 50,
            'event_date' => '2026-12-20',
            'venue_address' => '123 Test St',
            'payment_method' => 'online',
            'status' => 'Pending',
            'total_price' => 4499,
        ]);

        [$raw, $header] = $this->sign($booking->booking_reference);
        $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => $header,
        ], $raw)->assertOk();

        $this->assertDatabaseHas('notifications', ['data->type' => 'payment.confirmed']);
    }

    public function test_verified_paid_webhook_without_match_notifies_no_match()
    {
        config(['services.paymongo.webhook_secret' => $this->secret]);

        [$raw, $header] = $this->sign('INEA-GHOST', 'link.payment.paid', 'evt_ghost_1');
        $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => $header,
        ], $raw)->assertOk();

        $this->assertDatabaseHas('notifications', ['data->type' => 'webhook.no_match']);
    }

    public function test_verified_unknown_type_webhook_notifies_ignored()
    {
        config(['services.paymongo.webhook_secret' => $this->secret]);

        [$raw, $header] = $this->sign('INEA-X', 'link.updated', 'evt_ignored_1');
        $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => $header,
        ], $raw)->assertOk();

        $this->assertDatabaseHas('notifications', ['data->type' => 'webhook.ignored']);
    }

    public function test_expire_cron_notifies_when_stale_bookings_die()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $booking = Booking::create([
            'booking_reference' => 'INEA-STALE-1',
            'user_id' => $user->id,
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'package_id' => $this->package->id,
            'pax' => 50,
            'event_date' => '2026-12-20',
            'venue_address' => '123 Test St',
            'payment_method' => 'online',
            'status' => 'Pending',
        ]);
        DB::table('bookings')->where('id', $booking->id)->update([
            'created_at' => now()->subMinutes(16),
            'updated_at' => now()->subMinutes(16),
        ]);

        $this->postJson('/api/bookings/expire', [], ['X-Cron-Token' => env('CRON_TOKEN')])->assertOk();

        $this->assertDatabaseHas('notifications', ['data->type' => 'booking.expired']);
    }

    public function test_expire_cron_silent_when_nothing_expires()
    {
        $this->postJson('/api/bookings/expire', [], ['X-Cron-Token' => env('CRON_TOKEN')])->assertOk();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_rejected_signature_does_not_notify()
    {
        $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['data' => ['id' => 'evt_probe']]))->assertStatus(401);

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_admin_can_list_and_mark_notifications_read()
    {
        $this->admin->notify(new \App\Notifications\AdminAlert(
            type: 'inquiry.submitted',
            title: 'New inquiry',
            body: 'Lead Person requested a consultation.',
        ));

        $response = $this->actingAs($this->admin)->getJson('/admin/notifications');

        $response->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'notifications');

        $id = $response->json('notifications.0.id');

        $this->actingAs($this->admin)->postJson('/admin/notifications/read', ['ids' => [$id]])->assertOk();

        $this->assertSame(0, $this->admin->fresh()->unreadNotifications()->count());
    }

    public function test_admin_can_mark_all_notifications_read()
    {
        $this->admin->notify(new \App\Notifications\AdminAlert(
            type: 'inquiry.submitted',
            title: 'New inquiry',
            body: 'Lead Person requested a consultation.',
        ));

        $this->actingAs($this->admin)->postJson('/admin/notifications/read', [])->assertOk();

        $this->assertSame(0, $this->admin->fresh()->unreadNotifications()->count());
    }

    private function sign(string $reference, string $type = 'link.payment.paid', string $eventId = 'evt_test_1'): array
    {
        $raw = json_encode(['data' => [
            'id' => $eventId,
            'attributes' => [
                'type' => $type,
                'data' => ['attributes' => ['remarks' => $reference]],
            ],
        ]]);
        $timestamp = (string) time();
        $sig = hash_hmac('sha256', $timestamp . '.' . $raw, $this->secret);

        return [$raw, "t={$timestamp},te={$sig},li={$sig}"];
    }
}
