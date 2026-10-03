<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** The teams that sign off deal status changes, each identified by a permission. */
enum StatusApprovalTeam: string
{
    use HasOptions;

    case Management = 'management';
    case Accounts = 'accounts';

    public function label(): string
    {
        return match ($this) {
            self::Management => 'Management',
            self::Accounts => 'Accounts',
        };
    }

    public function permission(): string
    {
        return match ($this) {
            self::Management => 'deals.status.approve_management',
            self::Accounts => 'deals.status.approve_accounts',
        };
    }
}
