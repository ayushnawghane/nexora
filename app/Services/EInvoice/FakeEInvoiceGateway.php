<?php

namespace App\Services\EInvoice;

use App\Models\Invoice;
use Carbon\CarbonImmutable;

/**
 * Made-up IRNs for development and tests: the same invoice number always gets the same IRN, shaped
 * like a real one (64 hex characters). Nothing leaves the machine.
 */
class FakeEInvoiceGateway implements EInvoiceGateway
{
    public function register(Invoice $invoice): ?Irn
    {
        if ($invoice->number === null) {
            throw new EInvoiceFailed('The invoice has no number yet.');
        }
        $irn = hash('sha256', "fake-irn|{$invoice->number}");

        return new Irn(
            irn: $irn,
            ackNo: (string) (100000000000000 + hexdec(substr($irn, 0, 10)) % 899999999999999),
            ackAt: CarbonImmutable::now(),
            signedQr: 'FAKE.'.base64_encode(json_encode(['Irn' => $irn, 'DocNo' => $invoice->number, 'TotInvVal' => $invoice->total], JSON_THROW_ON_ERROR)),
        );
    }

    public function cancel(Invoice $invoice, string $reason): void
    {
        // Nothing to cancel anywhere.
    }
}
