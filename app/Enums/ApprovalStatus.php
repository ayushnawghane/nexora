<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ApprovalStatus: string
{
    use HasOptions;

    case Open = 'open';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Awaiting votes',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
