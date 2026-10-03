<?php

namespace App\Http\Requests\Companies;

use App\Enums\AddressType;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\CompanyGstin;
use App\Models\Pincode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * An address linked to a GSTIN must be in that GSTIN's state (it decides CGST+SGST vs IGST on invoices),
 * and a known pincode must be in the chosen state. A company has at most one active registered office.
 */
class CompanyAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->company());
    }

    protected function prepareForValidation(): void
    {
        $trim = fn (string $key) => trim((string) $this->input($key)) ?: null;

        $this->merge([
            'billing_name' => $trim('billing_name'),
            'line1' => $trim('line1'),
            'line2' => $trim('line2'),
            'city' => $trim('city'),
            'pincode' => preg_replace('/\s+/', '', (string) $this->input('pincode')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AddressType::class)],
            'billing_name' => ['nullable', 'string', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'pincode' => ['required', 'regex:/^[1-9][0-9]{5}$/'],
            'state_id' => ['required', 'integer', Rule::exists('states', 'id')],
            'company_gstin_id' => [
                'nullable', 'integer',
                Rule::exists('company_gstins', 'id')
                    ->where('company_id', $this->company()->id)
                    ->whereNull('deleted_at')
                    // An inactive GSTIN can stay on an address that already uses it, but can't be newly linked.
                    ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $this->editing()->company_gstin_id ?? 0)),
            ],
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

            $stateId = (int) $this->input('state_id');

            if ($this->filled('company_gstin_id')) {
                $gstin = CompanyGstin::query()->with('state')->findOrFail((int) $this->input('company_gstin_id'));
                if ($gstin->state_id !== $stateId) {
                    $validator->errors()->add('state_id', 'GSTIN '.$gstin->gstin.' is registered in '.$gstin->state->name.'. The address must be in the same state.');
                }
            }

            $pincodeStates = Pincode::query()->active()->where('pincode', $this->input('pincode'))->pluck('state_id')->unique();
            if ($pincodeStates->isNotEmpty() && ! $pincodeStates->contains($stateId)) {
                $validator->errors()->add('pincode', 'This pincode belongs to a different state. Check the pincode or the state.');
            }

            if ($this->input('type') === AddressType::Registered->value
                && $this->company()->addresses()->active()
                    ->where('type', AddressType::Registered)
                    ->when($this->editing(), fn ($q, CompanyAddress $address) => $q->whereKeyNot($address->id))
                    ->exists()) {
                $validator->errors()->add('type', 'This company already has an active registered office. Edit or deactivate that one first.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'pincode.regex' => 'A pincode is 6 digits and doesn\'t start with 0.',
            'company_gstin_id.exists' => 'Pick an active GSTIN of this company.',
        ];
    }

    public function attributes(): array
    {
        return ['line1' => 'address line 1', 'line2' => 'address line 2', 'state_id' => 'state', 'company_gstin_id' => 'GSTIN'];
    }

    public function company(): Company
    {
        /** @var Company */
        return $this->route('company');
    }

    public function editing(): ?CompanyAddress
    {
        /** @var CompanyAddress|null */
        return $this->route('address');
    }
}
