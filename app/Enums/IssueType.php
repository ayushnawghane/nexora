<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum IssueType: string
{
    use HasOptions;

    case PrivatePlacement = 'private_placement';
    case PublicIssue = 'public_issue';

    public function label(): string
    {
        return match ($this) {
            self::PrivatePlacement => 'Private placement',
            self::PublicIssue => 'Public issue',
        };
    }
}
