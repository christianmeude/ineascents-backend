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
