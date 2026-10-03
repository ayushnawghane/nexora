<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** How a contact receives the engagement letter and deal mail. */
enum Recipient: string
{
    use HasOptions;

    case To = 'to';
    case Cc = 'cc';

    public function label(): string
    {
        return match ($this) {
            self::To => 'To',
            self::Cc => 'Cc',
        };
    }
}
