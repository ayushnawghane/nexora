<?php

namespace App\GodMode\Editors;

use App\Enums\CouponType;
use App\Enums\DayCount;
use App\Enums\HolidayConvention;
use App\Enums\Listing;
use App\Enums\PaymentFrequency;
use App\Enums\Placement;
use App\GodMode\Editor;
use App\GodMode\Options;
use App\Http\Requests\Deals\Isin\IsinRequest;
use App\Models\DealIsin;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Corrects an ISIN's details with the normal form's rules, on any deal. Its allotments and
 * schedule stay as they are.
 *
 * @extends Editor<DealIsin>
 */
class DealIsinEditor extends Editor
{
    public function key(): string
    {
        return 'deal-isin';
    }

    public function label(): string
    {
        return 'ISIN';
    }

    public function modelClass(): string
    {
        return DealIsin::class;
    }

    protected function request(): string
    {
        return IsinRequest::class;
    }

    protected function routeParameters(Model $record): array
    {
        return ['transaction' => $record->transaction];
    }

    public function values(Model $record): array
    {
        return [
            'isin' => $record->isin,
            'series_name' => $record->series_name,
            'listing' => $record->listing?->value,
            'exchange' => $record->exchange,
            'depository' => $record->depository,
            'placement' => $record->placement?->value,
            'allotment_date' => $record->allotment_date?->toDateString(),
            'maturity_date' => $record->maturity_date?->toDateString(),
            'coupon_type' => $record->coupon_type?->value,
            'coupon_rate' => $record->coupon_rate,
            'coupon_description' => $record->coupon_description,
            'interest_frequency' => $record->interest_frequency?->value,
            'principal_frequency' => $record->principal_frequency?->value,
            'day_count' => $record->day_count?->value,
            'holiday_convention' => $record->holiday_convention?->value,
            'put_date' => $record->put_date?->toDateString(),
            'call_date' => $record->call_date?->toDateString(),
            'comments' => $record->comments,
        ];
    }

    public function fields(Model $record): array
    {
        $plain = fn (array $values) => Options::plain($values);

        return [
            ['name' => 'isin', 'label' => 'ISIN', 'type' => 'text', 'required' => true],
            ['name' => 'series_name', 'label' => 'Series', 'type' => 'textarea'],
            ['name' => 'listing', 'label' => 'Listing', 'type' => 'select', 'options' => Options::enum(Listing::class)],
            ['name' => 'exchange', 'label' => 'Exchange', 'type' => 'select', 'options' => $plain(['BSE', 'NSE', 'Both'])],
            ['name' => 'depository', 'label' => 'Depository', 'type' => 'select', 'options' => $plain(['NSDL', 'CDSL', 'Both'])],
            ['name' => 'placement', 'label' => 'Placement', 'type' => 'select', 'options' => Options::enum(Placement::class)],
            ['name' => 'allotment_date', 'label' => 'Allotment date', 'type' => 'date'],
            ['name' => 'maturity_date', 'label' => 'Maturity date', 'type' => 'date'],
            ['name' => 'coupon_type', 'label' => 'Coupon', 'type' => 'select', 'options' => Options::enum(CouponType::class)],
            ['name' => 'coupon_rate', 'label' => 'Coupon rate (%)', 'type' => 'number'],
            ['name' => 'coupon_description', 'label' => 'Coupon notes', 'type' => 'text'],
            ['name' => 'interest_frequency', 'label' => 'Interest paid', 'type' => 'select', 'options' => Options::enum(PaymentFrequency::class)],
            ['name' => 'principal_frequency', 'label' => 'Principal repaid', 'type' => 'select', 'options' => Options::enum(PaymentFrequency::class)],
            ['name' => 'day_count', 'label' => 'Day count', 'type' => 'select', 'options' => Options::enum(DayCount::class)],
            ['name' => 'holiday_convention', 'label' => 'Due date on a weekend', 'type' => 'select', 'options' => Options::enum(HolidayConvention::class)],
            ['name' => 'put_date', 'label' => 'Put date', 'type' => 'date'],
            ['name' => 'call_date', 'label' => 'Call date', 'type' => 'date'],
            ['name' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
        ];
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        if (DealIsin::query()->where('transaction_id', $record->transaction_id)->where('isin', $validated['isin'])->whereKeyNot($record->id)->exists()) {
            throw ValidationException::withMessages(['isin' => 'This ISIN is already on the deal.']);
        }
        $record->update($validated);
    }

    public function transactionId(Model $record): int
    {
        return $record->transaction_id;
    }
}
