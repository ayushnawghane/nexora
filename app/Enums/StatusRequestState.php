<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum StatusRequestState: string
{
    use HasOptions;

    case Open = 'open';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Awaiting approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Withdrawn => 'neutral',
        };
    }
}
