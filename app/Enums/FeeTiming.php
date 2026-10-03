<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FeeTiming: string
{
    use HasOptions;

    case Advance = 'advance';
    case Arrears = 'arrears';

    public function label(): string
    {
        return match ($this) {
            self::Advance => 'Payable in advance',
            self::Arrears => 'Payable in arrears',
        };
    }
}
