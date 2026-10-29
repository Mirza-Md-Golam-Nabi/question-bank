<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// No worker runs all the time on shared hosting: the server's cron runs
// `php artisan schedule:run` every minute, and that empties the queue (the
// background duplicate check) and stops. One at a time, so a long run is
// never joined by a second.
Schedule::command('queue:work --stop-when-empty --max-time=55 --timeout=600 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(15)
    ->runInBackground();
