<?php

namespace App\Http\Requests\Deals;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;

/** That the address, GSTIN and contacts belong to the deal's company is checked by SaveDealBilling. */
class DealBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Transaction $deal */
        $deal = $this->route('transaction');

        return $this->user()->can('editDeal', $deal);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_address_id' => ['required', 'integer'],
            'company_gstin_id' => ['nullable', 'integer'],
            'contact_ids' => ['required', 'array', 'min:1', 'max:20'],
            'contact_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return ['contact_ids.required' => 'Choose at least one billing contact.', 'contact_ids.min' => 'Choose at least one billing contact.'];
    }

    public function attributes(): array
    {
        return ['company_address_id' => 'billing address', 'company_gstin_id' => 'GSTIN', 'contact_ids' => 'billing contacts'];
    }
}
