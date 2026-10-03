<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EscalationType: string
{
    use HasOptions;

    case None = 'none';
    case Percent = 'percent';
    case Amount = 'amount';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No escalation',
            self::Percent => 'Percentage increase',
            self::Amount => 'Fixed amount increase (₹)',
        };
    }
}
