<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** A schedule row is an interest or a principal (redemption) payment. */
enum IsinPaymentKind: string
{
    use HasOptions;

    case Interest = 'interest';
    case Principal = 'principal';

    public function label(): string
    {
        return match ($this) {
            self::Interest => 'Interest',
            self::Principal => 'Principal',
        };
    }
}
