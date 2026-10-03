<?php

namespace App\Services\Tax;

/**
 * GST on one taxable amount. Amounts are decimal strings with 2 places (never floats).
 * Intra-state supplies carry CGST + SGST; inter-state supplies carry IGST.
 */
final readonly class GstBreakdown
{
    public function __construct(
        public bool $interState,
        public string $taxable,
        public string $cgstRate,
        public string $sgstRate,
        public string $igstRate,
        public string $cgst,
        public string $sgst,
        public string $igst,
        public string $totalTax,
        public string $total,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'inter_state' => $this->interState,
            'taxable' => $this->taxable,
            'cgst_rate' => $this->cgstRate,
            'sgst_rate' => $this->sgstRate,
            'igst_rate' => $this->igstRate,
            'cgst' => $this->cgst,
            'sgst' => $this->sgst,
            'igst' => $this->igst,
            'total_tax' => $this->totalTax,
            'total' => $this->total,
        ];
    }
}
