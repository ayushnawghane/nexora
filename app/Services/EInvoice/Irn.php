<?php

namespace App\Services\EInvoice;

use Carbon\CarbonImmutable;

/** What the IRP returns for a registered invoice. */
final readonly class Irn
{
    public function __construct(
        public string $irn,
        public string $ackNo,
        public CarbonImmutable $ackAt,
        public string $signedQr,
    ) {}
}
