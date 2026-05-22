<?php

use App\Mail\TestNotificationMail;
use App\Services\ReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reminders:send-due', function (): int {
    $sent = app(ReminderService::class)->sendDueReminders();
    $this->info("{$sent} due reminder emails sent.");

    return self::SUCCESS;
})->purpose('Send due tender proposal and quotation reminders');

Artisan::command('mail:test {recipient?}', function (): int {
    $recipient = $this->argument('recipient') ?: config('mail.from.address');

    if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        $this->error('Provide a valid recipient email address or configure MAIL_FROM_ADDRESS.');

        return self::FAILURE;
    }

    try {
        Mail::to($recipient)->send(new TestNotificationMail);
    } catch (Throwable $exception) {
        $this->error('Test email failed: '.$exception->getMessage());

        return self::FAILURE;
    }

    $this->info("Test email sent to {$recipient}.");

    return self::SUCCESS;
})->purpose('Send a branded development test email');

Schedule::command('reminders:send-due')->dailyAt('08:00');
