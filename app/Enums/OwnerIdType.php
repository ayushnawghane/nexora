<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** How the owner of a secured asset is identified. */
enum OwnerIdType: string
{
    use HasOptions;

    case Cin = 'cin';
    case Pan = 'pan';

    public function label(): string
    {
        return match ($this) {
            self::Cin => 'CIN / LLPIN',
            self::Pan => 'PAN',
        };
    }
}
