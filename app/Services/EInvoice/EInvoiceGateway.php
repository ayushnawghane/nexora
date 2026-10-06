<?php

namespace App\Services\EInvoice;

use App\Models\Invoice;

/**
 * The GST invoice registration portal (IRP). A tax invoice or credit note billed to a GSTIN is
 * registered there and gets its IRN and signed QR code. Drivers: "fake" (development and tests),
 * "none" (no e-invoicing); the IRIS driver comes with the UAT credentials.
 */
interface EInvoiceGateway
{
    /**
     * Registers the invoice (number, date, parties, lines and tax already set). Null when this
     * driver doesn't e-invoice.
     *
     * @throws EInvoiceFailed when the IRP rejects it or can't be reached
     */
    public function register(Invoice $invoice): ?Irn;

    /**
     * Cancels the invoice's IRN (allowed within 24 hours of registration).
     *
     * @throws EInvoiceFailed
     */
    public function cancel(Invoice $invoice, string $reason): void;
}
