<?php

return [
    // Reminders go out for interest and principal falling due within this many days (and overdue).
    'reminder_days' => (int) env('ISIN_REMINDER_DAYS', 7),

    // Overdue payments are included for this many days after their due date, then only shown on the
    // dashboard and ISIN list (Stack left thousands of old payments marked due; they'd be emailed daily).
    'reminder_overdue_days' => (int) env('ISIN_REMINDER_OVERDUE_DAYS', 30),

    // When the daily reminder job runs (24-hour time; Stack sent its ISIN alerts at 08:30).
    'reminder_time' => env('ISIN_REMINDER_TIME', '08:30'),
];
