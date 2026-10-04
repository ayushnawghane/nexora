<?php

namespace App\Http\Requests\Deals\Isin;

use App\Enums\CouponType;
use App\Enums\DayCount;
use App\Enums\HolidayConvention;
use App\Enums\Listing;
use App\Enums\PaymentFrequency;
use App\Enums\Placement;
use App\Http\Requests\Deals\Documents\DocumentsRequest;
use App\Support\IndianIdentifiers;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IsinRequest extends DocumentsRequest
{
    protected string $ability = 'manageIsin';

    protected function prepareForValidation(): void
    {
        $clean = [];
        if (is_string($this->input('isin'))) {
            $clean['isin'] = IndianIdentifiers::normalise($this->input('isin'));
        }
        foreach ($this->all() as $key => $value) {
            if ($value === '') {
                $clean[$key] = null;
            } elseif (is_string($value) && $key !== 'isin') {
                $clean[$key] = trim($value);
            }
        }
        $this->merge($clean);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $date = ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'];

        return [
            'isin' => ['required', 'string', 'size:12'],
            'series_name' => ['nullable', 'string', 'max:2000'],
            'listing' => ['nullable', Rule::enum(Listing::class)],
            'exchange' => ['nullable', Rule::in(['BSE', 'NSE', 'Both'])],
            'depository' => ['nullable', Rule::in(['NSDL', 'CDSL', 'Both'])],
            'placement' => ['nullable', Rule::enum(Placement::class)],
            'allotment_date' => $date,
            'maturity_date' => [...$date, 'after_or_equal:allotment_date'],
            'coupon_type' => ['nullable', Rule::enum(CouponType::class)],
            'coupon_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
            'coupon_description' => ['nullable', 'string', 'max:255'],
            'interest_frequency' => ['nullable', Rule::enum(PaymentFrequency::class)],
            'principal_frequency' => ['nullable', Rule::enum(PaymentFrequency::class)],
            'day_count' => ['nullable', Rule::enum(DayCount::class)],
            'holiday_convention' => ['nullable', Rule::enum(HolidayConvention::class)],
            'put_date' => [...$date, 'after_or_equal:allotment_date', 'before_or_equal:maturity_date'],
            'call_date' => [...$date, 'after_or_equal:allotment_date', 'before_or_equal:maturity_date'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $isin = (string) $this->input('isin');
            if ($isin !== '' && ! $validator->errors()->has('isin') && ! IndianIdentifiers::isIsin($isin)) {
                $validator->errors()->add('isin', 'Enter a valid ISIN (IN, 9 letters or digits, and a check digit).');
            }
            if ($this->input('coupon_type') === CouponType::Zero->value && (float) $this->input('coupon_rate') > 0) {
                $validator->errors()->add('coupon_rate', 'A zero-coupon ISIN has no coupon rate.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'maturity_date.after_or_equal' => 'Maturity can\'t be before the allotment date.',
            'put_date.before_or_equal' => 'The put date must be on or before maturity.',
            'call_date.before_or_equal' => 'The call date must be on or before maturity.',
        ];
    }
}
