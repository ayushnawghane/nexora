<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** How a principal payment redeems the debentures. */
enum RedemptionBasis: string
{
    use HasOptions;

    case Full = 'full';
    case FaceValue = 'face_value';
    case Quantity = 'quantity';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Full redemption',
            self::FaceValue => 'Part, by face value',
            self::Quantity => 'Part, by quantity',
        };
    }
}
