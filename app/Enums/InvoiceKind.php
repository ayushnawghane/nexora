<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** The kinds of billing document. Stack called reimbursement bills "debit notes" (numbered DN). */
enum InvoiceKind: string
{
    use HasOptions;

    case Proforma = 'proforma';
    case Tax = 'tax';
    case CreditNote = 'credit_note';
    case Reimbursement = 'reimbursement';

    public function label(): string
    {
        return match ($this) {
            self::Proforma => 'Proforma invoice',
            self::Tax => 'Tax invoice',
            self::CreditNote => 'Credit note',
            self::Reimbursement => 'Reimbursement bill',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Proforma => 'Proforma',
            self::Tax => 'Tax invoice',
            self::CreditNote => 'Credit note',
            self::Reimbursement => 'Reimbursement',
        };
    }

    /** The number prefix after the financial year (config billing.number_format). */
    public function code(): string
    {
        return match ($this) {
            self::Proforma => 'INV',
            self::Tax => 'TAX',
            self::CreditNote => 'CN',
            self::Reimbursement => 'DN',
        };
    }

    /**
     * Money is collected against these (as Stack did): the proforma is the demand, and its tax
     * invoice follows once paid; credit notes on that tax invoice reduce what the proforma is owed.
     */
    public function isReceivable(): bool
    {
        return $this === self::Proforma || $this === self::Reimbursement;
    }

    /** These go to the GST e-invoice portal (IRP) for an IRN when the client has a GSTIN. */
    public function needsIrn(): bool
    {
        return $this === self::Tax || $this === self::CreditNote;
    }
}
