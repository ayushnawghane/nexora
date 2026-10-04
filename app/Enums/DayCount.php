<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** The day-count convention interest is calculated on. */
enum DayCount: string
{
    use HasOptions;

    case Thirty360 = '30_360';
    case Actual365 = 'act_365';
    case ActualActual = 'act_act';

    public function label(): string
    {
        return match ($this) {
            self::Thirty360 => '30/360',
            self::Actual365 => 'Actual/365',
            self::ActualActual => 'Actual/Actual',
        };
    }
}
