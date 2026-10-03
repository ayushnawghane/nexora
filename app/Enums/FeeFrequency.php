<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** How often a fee is billed. Recurring fees are quoted per annum and billed in these periods. */
enum FeeFrequency: string
{
    use HasOptions;

    case OneTime = 'one_time';
    case Annual = 'annual';
    case HalfYearly = 'half_yearly';
    case Quarterly = 'quarterly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'One time',
            self::Annual => 'Annually',
            self::HalfYearly => 'Half-yearly',
            self::Quarterly => 'Quarterly',
            self::Monthly => 'Monthly',
        };
    }

    /** Length of one billing period in months (null for one-time fees). */
    public function months(): ?int
    {
        return match ($this) {
            self::OneTime => null,
            self::Annual => 12,
            self::HalfYearly => 6,
            self::Quarterly => 3,
            self::Monthly => 1,
        };
    }
}
