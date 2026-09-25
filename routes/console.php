<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// M6 - Leave proof reminders
Schedule::command('leave:send-proof-reminders')->hourly();

// M7 - Asset overdue escalation
Schedule::command('assets:check-overdue')->dailyAt('08:00');

// M7 - Contract expiry alerts
Schedule::command('contracts:check-expiry')->dailyAt('06:00');

// M11 - Reminder generation
Schedule::command('reminders:generate --type=timesheet')->everyFiveDays();
Schedule::command('reminders:generate --type=timesheet --urgent')->monthlyOn(27, '08:00');
Schedule::command('reminders:generate --type=contract')->dailyAt('07:00');