<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** What happens to a due date that falls on a weekend. */
enum HolidayConvention: string
{
    use HasOptions;

    case None = 'none';
    case Preceding = 'preceding';
    case Following = 'following';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No adjustment',
            self::Preceding => 'Previous working day',
            self::Following => 'Next working day',
        };
    }
}
