<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// A20: nightly retention enforcement (Render cron calls the endpoint;
// scheduler covers long-lived dynos too).
Schedule::command('privacy:purge')->dailyAt('03:00');

// Expire stale pending bookings every 5 minutes
Schedule::call(function () {
    $expired = \App\Models\Booking::expireStalePending();
    if ($expired > 0) {
        \Illuminate\Support\Facades\Log::info("Expired {$expired} stale pending bookings via scheduler.");
    }
})->everyFiveMinutes();
