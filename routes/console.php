<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Vesper Automated Lifecycle & SiteGround Scheduled Tasks
Schedule::command('vesper:channel-burn-check')->everyFifteenMinutes();
Schedule::command('vesper:send-operations-digest')->daily();
Schedule::command('vesper:recompute-ranks')->dailyAt('03:00');
Schedule::command('vesper:close-ban-cases')->everyFiveMinutes();

// SiteGround-compatible worker: runs queued emails without needing a 24/7 supervisor daemon
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
