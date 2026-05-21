<?php

use App\Services\ReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reminders:send-due', function (): int {
    $sent = app(ReminderService::class)->sendDueReminders();
    $this->info("{$sent} due reminder emails sent.");

    return self::SUCCESS;
})->purpose('Send due project and quotation reminders');

Schedule::command('reminders:send-due')->dailyAt('08:00');
