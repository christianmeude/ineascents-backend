<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PrivacyPurgeTest extends TestCase
{
    use RefreshDatabase;

    private function backdate(string $table, int $id, string $column, string $when): void
    {
        DB::table($table)->where('id', $id)->update([
            'created_at' => $when,
            'updated_at' => $when,
        ]);
    }

    public function test_dry_run_counts_without_writing(): void
    {
        $package = Package::create(['name' => 'P', 'description' => 'D', 'price' => 100]);

        $old = Inquiry::create([
            'name' => 'Old', 'email' => 'old@example.com', 'phone' => '0917',
            'consent_privacy_version' => '2026-10-01', 'consented_at' => now()->subMonths(30),
        ]);
        $this->backdate('inquiries', $old->id, 'updated_at', now()->subMonths(25)->toDateTimeString());

        $this->artisan('privacy:purge', ['--dry-run' => true])
            ->expectsTable(['scope', 'matches'], [
                ['inquiries >24mo untouched', 1],
                ['bookings >36mo untouched', 0],
                ['webhook_events >12mo', 0],
            ])
            ->assertExitCode(0);

        $this->assertDatabaseHas('inquiries', ['email' => 'old@example.com']);
    }

    public function test_purge_scrubs_stale_rows_and_drops_old_webhooks(): void
    {
        $package = Package::create(['name' => 'P', 'description' => 'D', 'price' => 100]);

        $oldInquiry = Inquiry::create([
            'name' => 'Old', 'email' => 'old@example.com', 'phone' => '0917',
            'consent_privacy_version' => '2026-10-01', 'consented_at' => now()->subMonths(30),
        ]);
        $this->backdate('inquiries', $oldInquiry->id, 'updated_at', now()->subMonths(25)->toDateTimeString());

        $freshInquiry = Inquiry::create([
            'name' => 'Fresh', 'email' => 'fresh@example.com', 'phone' => '0917',
            'consent_privacy_version' => '2026-10-01', 'consented_at' => now(),
        ]);

        $oldBooking = Booking::create([
            'booking_reference' => 'BOOKING-OLD', 'package_id' => $package->id,
            'customer_name' => 'Old', 'customer_email' => 'old@example.com',
            'pax' => 2, 'event_date' => '2023-01-10', 'venue_address' => 'Old St',
            'payment_method' => 'cash',
            'consent_privacy_version' => '2026-10-01', 'consented_at' => now()->subMonths(40),
        ]);
        $this->backdate('bookings', $oldBooking->id, 'updated_at', now()->subMonths(37)->toDateTimeString());

        $oldEvent = DB::table('webhook_events')->insertGetId([
            'event_id' => 'evt_old', 'event_type' => 'payment.paid',
            'payload' => '{}', 'created_at' => now()->subMonths(13), 'updated_at' => now()->subMonths(13),
        ]);

        $this->artisan('privacy:purge')->assertExitCode(0);

        $this->assertDatabaseMissing('inquiries', ['email' => 'old@example.com']);
        $this->assertDatabaseHas('inquiries', ['email' => 'fresh@example.com']);
        $this->assertDatabaseMissing('bookings', ['customer_email' => 'old@example.com']);
        $this->assertDatabaseMissing('webhook_events', ['id' => $oldEvent]);
        $this->assertDatabaseCount('inquiries', 2);
        $this->assertDatabaseCount('bookings', 1);
    }
}
