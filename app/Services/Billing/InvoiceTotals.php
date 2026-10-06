<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Services\Tax\GstCalculator;
use App\Services\Tax\TaxNotConfigured;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Works out an invoice's amounts from its lines: taxable lines carry GST (CGST + SGST within
 * Beacon's home state, IGST outside it, each rounded to paise half up), expense lines don't.
 */
class InvoiceTotals
{
    public function __construct(private readonly GstCalculator $gst) {}

    /**
     * Amounts at the GST rate in force on $date, for the invoice's place of supply.
     *
     * @param  Collection<int, InvoiceLine>  $lines
     * @return array<string, mixed> invoice attributes
     */
    public function on(Invoice $invoice, Collection $lines, DateTimeInterface $date): array
    {
        if (! $invoice->gst_applies) {
            return $this->with($lines, false, '0', '0', '0');
        }
        if ($invoice->place_of_supply_state_id === null) {
            throw ValidationException::withMessages(['billing' => 'The invoice has no place of supply: set who the deal is billed to.']);
        }

        try {
            $rate = $this->gst->rateOn($date);
            $interState = $invoice->place_of_supply_state_id !== $this->gst->homeStateId();
        } catch (TaxNotConfigured $e) {
            throw ValidationException::withMessages(['billing' => $e->getMessage()]);
        }

        return $interState
            ? $this->with($lines, true, '0', '0', $rate->igst)
            : $this->with($lines, false, $rate->cgst, $rate->sgst, '0');
    }

    /**
     * Amounts at fixed rates (a credit note reverses tax at the rates of the invoice it reduces).
     *
     * @param  Collection<int, InvoiceLine>  $lines
     * @return array<string, mixed>
     */
    public function at(Invoice $source, Collection $lines): array
    {
        return $this->with($lines, (bool) $source->inter_state, $source->cgst_rate, $source->sgst_rate, $source->igst_rate);
    }

    /**
     * @param  Collection<int, InvoiceLine>  $lines
     * @return array<string, mixed>
     */
    private function with(Collection $lines, bool $interState, string $cgstRate, string $sgstRate, string $igstRate): array
    {
        $taxable = BigDecimal::zero()->toScale(2);
        $other = BigDecimal::zero()->toScale(2);
        foreach ($lines as $line) {
            $line->taxable ? $taxable = $taxable->plus($line->amount) : $other = $other->plus($line->amount);
        }

        $percent = fn (string $rate) => $taxable->multipliedBy($rate)->dividedBy(100, 2, RoundingMode::HalfUp);
        $cgst = $percent($cgstRate);
        $sgst = $percent($sgstRate);
        $igst = $percent($igstRate);

        return [
            'inter_state' => $interState,
            'taxable_amount' => (string) $taxable,
            'non_taxable_amount' => (string) $other,
            'cgst_rate' => $cgstRate,
            'sgst_rate' => $sgstRate,
            'igst_rate' => $igstRate,
            'cgst' => (string) $cgst,
            'sgst' => (string) $sgst,
            'igst' => (string) $igst,
            'total' => (string) $taxable->plus($other)->plus($cgst)->plus($sgst)->plus($igst),
        ];
    }
}
