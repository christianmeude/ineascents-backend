<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill Completed for past Confirmed bookings.
     * Cancelled rows are never touched (terminal, even past-date).
     */
    public function up(): void
    {
        $today = Carbon::now('Asia/Manila')->toDateString();

        DB::table('bookings')
            ->where('status', 'Confirmed')
            ->whereDate('event_date', '<', $today)
            ->update(['status' => 'Completed']);
    }

    public function down(): void
    {
        DB::table('bookings')
            ->where('status', 'Completed')
            ->update(['status' => 'Confirmed']);
    }
};
