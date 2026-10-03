<?php

namespace App\Http\Requests\Companies;

use App\Models\Company;
use App\Models\CompanyGstin;
use App\Models\State;
use App\Support\IndianIdentifiers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The GSTIN itself is fixed once saved (addresses and invoices hang off it); only its details can be edited.
 * The state is never typed in: it comes from the GSTIN's first two digits.
 */
class CompanyGstinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->company());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'gstin' => IndianIdentifiers::normalise($this->input('gstin')),
            'legal_name' => trim((string) $this->input('legal_name')) ?: null,
            'trade_name' => trim((string) $this->input('trade_name')) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'gstin' => $this->editing()
                ? ['nullable', Rule::in([$this->editing()->gstin])]
                : ['required', 'string', 'size:15', 'regex:'.IndianIdentifiers::GSTIN_PATTERN, Rule::unique('company_gstins', 'gstin')],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'registered_on' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:2017-07-01'],
        ];
    }

    /**
     * @return list<\Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->editing() || $validator->errors()->has('gstin')) {
                return;
            }

            $gstin = (string) $this->input('gstin');
            $company = $this->company();

            if (! IndianIdentifiers::isGstin($gstin)) {
                $validator->errors()->add('gstin', 'This GSTIN fails its check-digit test. Check it for typing mistakes.');

                return;
            }

            if (! State::query()->where('gst_code', IndianIdentifiers::gstinStateCode($gstin))->exists()) {
                $validator->errors()->add('gstin', 'The first two digits ('.IndianIdentifiers::gstinStateCode($gstin).') are not a GST state code.');
            }

            if ($company->pan === null) {
                $validator->errors()->add('gstin', 'Add the company\'s PAN before adding a GSTIN.');
            } elseif (IndianIdentifiers::gstinPan($gstin) !== $company->pan) {
                $validator->errors()->add('gstin', 'This GSTIN belongs to PAN '.IndianIdentifiers::gstinPan($gstin).', not the company\'s PAN '.$company->pan.'.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'gstin.size' => 'A GSTIN is 15 characters.',
            'gstin.regex' => 'A GSTIN looks like 27AAACB1234C1Z5.',
            'gstin.unique' => 'This GSTIN is already registered to a company.',
            'gstin.in' => 'A saved GSTIN can\'t be changed. Deactivate it and add the correct one.',
            'registered_on.after_or_equal' => 'GST registrations start from 1 July 2017.',
        ];
    }

    public function company(): Company
    {
        /** @var Company */
        return $this->route('company');
    }

    public function editing(): ?CompanyGstin
    {
        /** @var CompanyGstin|null */
        return $this->route('gstin');
    }
}
