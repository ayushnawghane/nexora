<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Listing: string
{
    use HasOptions;

    case Listed = 'listed';
    case Unlisted = 'unlisted';

    public function label(): string
    {
        return match ($this) {
            self::Listed => 'Listed',
            self::Unlisted => 'Unlisted',
        };
    }
}
