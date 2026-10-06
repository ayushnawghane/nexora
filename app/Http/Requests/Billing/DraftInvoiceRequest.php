<?php

namespace App\Http\Requests\Billing;

use Illuminate\Validation\Rule;

/** A draft proforma or reimbursement bill: the fee periods, expenses and other fees it bills. */
class DraftInvoiceRequest extends BillingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => [$this->isMethod('post') ? 'required' : 'prohibited', Rule::in(['proforma', 'reimbursement'])],
            'periods' => ['array', 'max:60'],
            'periods.*' => ['integer', 'distinct'],
            'expenses' => ['array', 'max:100'],
            'expenses.*' => ['string', 'ulid', 'distinct'],
            'others' => ['array', 'max:10', Rule::prohibitedIf($this->input('kind') === 'reimbursement')],
            'others.*.description' => ['required', 'string', 'max:255'],
            'others.*.amount' => ['required', ...self::MONEY, 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'others.prohibited' => 'A reimbursement bill carries expenses only.',
            'others.*.description.required' => 'Describe the fee.',
            'others.*.amount.required' => 'Enter the amount.',
            'others.*.amount.gt' => 'The amount must be more than zero.',
        ];
    }
}
