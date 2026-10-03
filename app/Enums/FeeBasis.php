<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FeeBasis: string
{
    use HasOptions;

    case IssueSize = 'issue_size';
    case IssueSizeTranches = 'issue_size_tranches';
    case SubscribedAmount = 'subscribed_amount';
    case PerTranche = 'per_tranche';
    case PerSecurityDocument = 'per_security_document';

    public function label(): string
    {
        return match ($this) {
            self::IssueSize => 'Of issue size',
            self::IssueSizeTranches => 'Of issue size, in one or more tranches',
            self::SubscribedAmount => 'Of subscribed amount',
            self::PerTranche => 'Per tranche',
            self::PerSecurityDocument => 'Per security document',
        };
    }
}
