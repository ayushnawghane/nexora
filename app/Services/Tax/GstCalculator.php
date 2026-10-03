<?php

namespace App\Services\Tax;

use App\Models\Setting;
use App\Models\State;
use App\Models\TaxRate;
use App\Support\IndianIdentifiers;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Works out GST on a taxable amount. The place of supply (the billing address state) is compared
 * with Beacon's home state (from Beacon's GSTIN): same state → CGST + SGST, different → IGST.
 * Each tax component is rounded to paise, half up.
 */
class GstCalculator
{
    public function calculate(string $taxable, int $placeOfSupplyStateId, DateTimeInterface $date): GstBreakdown
    {
        $rate = $this->rateOn($date);
        $amount = BigDecimal::of($taxable)->toScale(2, RoundingMode::HalfUp);
        $interState = $placeOfSupplyStateId !== $this->homeStateId();

        $percent = fn (string $rate) => $amount->multipliedBy($rate)->dividedBy(100, 2, RoundingMode::HalfUp);
        $zero = BigDecimal::zero()->toScale(2);

        $cgst = $interState ? $zero : $percent($rate->cgst);
        $sgst = $interState ? $zero : $percent($rate->sgst);
        $igst = $interState ? $percent($rate->igst) : $zero;
        $totalTax = $cgst->plus($sgst)->plus($igst);

        return new GstBreakdown(
            interState: $interState,
            taxable: (string) $amount,
            cgstRate: $rate->cgst,
            sgstRate: $rate->sgst,
            igstRate: $rate->igst,
            cgst: (string) $cgst,
            sgst: (string) $sgst,
            igst: (string) $igst,
            totalTax: (string) $totalTax,
            total: (string) $amount->plus($totalTax),
        );
    }

    public function rateOn(DateTimeInterface $date): TaxRate
    {
        return TaxRate::query()->inForceOn($date)->first()
            ?? throw TaxNotConfigured::noRateOn(Carbon::instance($date)->toDateString());
    }

    public function homeStateId(): int
    {
        $gstin = Setting::value(Setting::BEACON_GSTIN);
        $stateId = $gstin
            ? State::query()->where('gst_code', IndianIdentifiers::gstinStateCode($gstin))->value('id')
            : null;

        return $stateId ?? throw TaxNotConfigured::noHomeState();
    }
}
