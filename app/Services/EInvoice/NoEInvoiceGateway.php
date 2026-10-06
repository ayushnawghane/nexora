<?php

namespace App\Services\EInvoice;

use App\Models\Invoice;

/** E-invoicing switched off: invoices are issued without an IRN. */
class NoEInvoiceGateway implements EInvoiceGateway
{
    public function register(Invoice $invoice): ?Irn
    {
        return null;
    }

    public function cancel(Invoice $invoice, string $reason): void {}
}
