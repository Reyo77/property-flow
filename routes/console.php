<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('announcements:publish-due')->everyMinute();
Schedule::command('maintenance:generate-due-work-orders')->daily();
Schedule::command('packages:remind-uncollected')->daily();
Schedule::command('finance:run-billing')->dailyAt('01:00');
Schedule::command('finance:assess-late-fees')->dailyAt('02:00');
Schedule::command('finance:send-overdue-reminders')->dailyAt('09:00');
Schedule::command('ballots:close-ended')->everyMinute();
