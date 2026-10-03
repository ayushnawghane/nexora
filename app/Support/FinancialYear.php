<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/** Indian financial year (1 April – 31 March) helpers. */
final class FinancialYear
{
    public static function startYear(DateTimeInterface $date): int
    {
        $d = CarbonImmutable::instance($date);

        return $d->month >= 4 ? $d->year : $d->year - 1;
    }

    /** "25-26" for any date from 1 April 2025 to 31 March 2026 (as used in EL numbers). */
    public static function short(DateTimeInterface $date): string
    {
        $start = self::startYear($date);

        return sprintf('%02d-%02d', $start % 100, ($start + 1) % 100);
    }
}
