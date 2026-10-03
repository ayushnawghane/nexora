<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FeeAmountType: string
{
    use HasOptions;

    case Fixed = 'fixed';
    case Percent = 'percent';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Amount (₹)',
            self::Percent => 'Percentage of issue size',
        };
    }
}
