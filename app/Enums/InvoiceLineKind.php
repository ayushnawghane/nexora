<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** What an invoice line charges for. Out-of-pocket expenses (OPE) are reimbursed without GST. */
enum InvoiceLineKind: string
{
    use HasOptions;

    case Acceptance = 'acceptance';
    case Service = 'service';
    case Other = 'other';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Acceptance => 'Acceptance fee',
            self::Service => 'Service fee',
            self::Other => 'Other fee',
            self::Expense => 'Out-of-pocket expense',
        };
    }

    public static function forFee(FeeKind $kind): self
    {
        return match ($kind) {
            FeeKind::Acceptance => self::Acceptance,
            FeeKind::Service => self::Service,
        };
    }
}
