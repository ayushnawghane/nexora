<?php

use App\Actions\Isin\SendPaymentReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ISIN payment reminders: one email per deal about payments due soon or overdue (see SendPaymentReminders).
Artisan::command('isin:send-reminders', function (SendPaymentReminders $reminders) {
    $this->info($reminders->daily().' reminder emails sent.');
})->purpose('Email deal teams about ISIN payments due soon or overdue');

Schedule::command('isin:send-reminders')->dailyAt((string) config('isin.reminder_time'))->withoutOverlapping();
