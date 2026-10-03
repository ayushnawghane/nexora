<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum VoteDecision: string
{
    use HasOptions;

    case Approve = 'approve';
    case Reject = 'reject';

    public function label(): string
    {
        return match ($this) {
            self::Approve => 'Approved',
            self::Reject => 'Rejected',
        };
    }
}
