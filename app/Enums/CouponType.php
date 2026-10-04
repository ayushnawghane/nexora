<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** How the coupon is set. */
enum CouponType: string
{
    use HasOptions;

    case Fixed = 'fixed';
    case Variable = 'variable';
    case Linked = 'linked';
    case Zero = 'zero';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed',
            self::Variable => 'Variable',
            self::Linked => 'Market linked',
            self::Zero => 'Zero coupon',
        };
    }
}
