<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily searches are emailed once a day as a single digest per buyer, so a busy
// day is one email rather than one per listing. Needs `php artisan schedule:work`
// (or the cron entry) to run on its own; run alerts:send-digests by hand otherwise.
Schedule::command('alerts:send-digests')->dailyAt('08:00');
