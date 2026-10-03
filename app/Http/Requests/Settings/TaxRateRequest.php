<?php

namespace App\Http\Requests\Settings;

use App\Models\TaxRate;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A new rate takes effect today or later, after every existing rate, so invoices already raised
 * keep the rate they were raised at. CGST and SGST are always equal halves of IGST under GST law.
 */
class TaxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $latest = TaxRate::query()->max('effective_from');

        return [
            'effective_from' => array_filter([
                'required', 'date', 'after_or_equal:today',
                $latest ? 'after:'.$latest : null,
            ]),
            'cgst' => ['required', 'decimal:0,2', 'between:0,50'],
            'sgst' => ['required', 'decimal:0,2', 'between:0,50'],
            'igst' => ['required', 'decimal:0,2', 'between:0,100'],
        ];
    }

    /**
     * @return list<\Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $cgst = BigDecimal::of((string) $this->input('cgst'));
            $sgst = BigDecimal::of((string) $this->input('sgst'));

            if (! $cgst->isEqualTo($sgst)) {
                $validator->errors()->add('sgst', 'SGST must equal CGST.');
            }
            if (! $cgst->plus($sgst)->isEqualTo((string) $this->input('igst'))) {
                $validator->errors()->add('igst', 'IGST must equal CGST + SGST.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'effective_from.after_or_equal' => 'A new rate can\'t take effect in the past; invoices already raised keep their rate.',
            'effective_from.after' => 'A new rate must take effect after the latest existing rate.',
        ];
    }

    public function attributes(): array
    {
        return ['cgst' => 'CGST', 'sgst' => 'SGST', 'igst' => 'IGST'];
    }
}
