<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Where a scheduled payment stands. Only a due payment can still change. */
enum IsinPaymentStatus: string
{
    use HasOptions;

    case Due = 'due';
    case Paid = 'paid';
    case Defaulted = 'defaulted';
    case RedeemedEarly = 'redeemed_early';

    public function label(): string
    {
        return match ($this) {
            self::Due => 'Due',
            self::Paid => 'Paid',
            self::Defaulted => 'Defaulted',
            self::RedeemedEarly => 'Redeemed earlier',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Due => 'warning',
            self::Paid => 'success',
            self::Defaulted => 'danger',
            self::RedeemedEarly => 'neutral',
        };
    }
}
