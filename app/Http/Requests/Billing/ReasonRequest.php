<?php

namespace App\Http\Requests\Billing;

/** Sending back or cancelling an invoice, or reversing a receipt: the reason is kept. */
class ReasonRequest extends BillingRequest
{
    protected string $permission = 'billing.approve';

    public function authorize(): bool
    {
        return $this->user()->can($this->routeIs('invoice-receipts.reverse') ? 'billing.receipts' : $this->permission);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:2000']];
    }
}
