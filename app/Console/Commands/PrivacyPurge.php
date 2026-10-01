<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Inquiry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * A20: retention schedule enforcement (owner windows 2026-10-01).
 * - inquiries untouched 24mo → anonymized
 * - bookings untouched 36mo → anonymized
 * - webhook_events older than 12mo → hard-deleted (no counts depend on them)
 * Already-scrubbed rows (@deleted.local) are skipped. --dry-run counts only.
 */
class PrivacyPurge extends Command
{
    protected $signature = 'privacy:purge {--dry-run : Count matches without writing}';

    protected $description = 'Anonymize PII past its retention window (24/36mo) and drop old webhook events (12mo)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $staleInquiries = Inquiry::where('updated_at', '<', now()->subMonths(24))
            ->where('email', 'not like', '%@deleted.local')
            ->count();

        $staleBookings = Booking::where('updated_at', '<', now()->subMonths(36))
            ->where('customer_email', 'not like', '%@deleted.local')
            ->count();

        $staleWebhooks = DB::table('webhook_events')
            ->where('created_at', '<', now()->subMonths(12))
            ->count();

        if ($dry) {
            $this->table(
                ['scope', 'matches'],
                [
                    ['inquiries >24mo untouched', $staleInquiries],
                    ['bookings >36mo untouched', $staleBookings],
                    ['webhook_events >12mo', $staleWebhooks],
                ]
            );

            return self::SUCCESS;
        }

        $scrubbedInquiries = 0;
        Inquiry::where('updated_at', '<', now()->subMonths(24))
            ->where('email', 'not like', '%@deleted.local')
            ->chunkById(200, function ($rows) use (&$scrubbedInquiries) {
                foreach ($rows as $inquiry) {
                    $inquiry->update([
                        'name' => 'Deleted Inquirer',
                        'email' => "deleted-inquiry-{$inquiry->id}@deleted.local",
                        'phone' => '0000000000',
                        'message' => null,
                    ]);
                    $scrubbedInquiries++;
                }
            });

        $scrubbedBookings = 0;
        Booking::where('updated_at', '<', now()->subMonths(36))
            ->where('customer_email', 'not like', '%@deleted.local')
            ->chunkById(200, function ($rows) use (&$scrubbedBookings) {
                foreach ($rows as $booking) {
                    $booking->update([
                        'customer_name' => 'Deleted Customer',
                        'customer_email' => "deleted-booking-{$booking->id}@deleted.local",
                        'customer_phone' => null,
                        'venue_address' => 'Deleted Venue',
                        'notes' => null,
                        'checkout_url' => null,
                    ]);
                    $scrubbedBookings++;
                }
            });

        $droppedWebhooks = 0;
        do {
            $dropped = DB::table('webhook_events')
                ->where('created_at', '<', now()->subMonths(12))
                ->limit(500)
                ->delete();
            $droppedWebhooks += $dropped;
        } while ($dropped > 0);

        $this->info("Purged: {$scrubbedInquiries} inquiries, {$scrubbedBookings} bookings anonymized, {$droppedWebhooks} webhook events deleted.");

        return self::SUCCESS;
    }
}
