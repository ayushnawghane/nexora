<?php

namespace App\Http\Requests\Billing;

/** Money received against an invoice, and the TDS deducted. */
class ReceiptRequest extends BillingRequest
{
    protected string $permission = 'billing.receipts';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'received_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'amount' => ['required', ...self::MONEY],
            'tds_amount' => ['nullable', ...self::MONEY],
            'utr' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-\/]+$/'],
            'remark' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'received_on.before_or_equal' => 'The date received can\'t be in the future.',
            'utr.regex' => 'The UTR / reference can only have letters, digits, - and /.',
        ];
    }

    public function attributes(): array
    {
        return ['received_on' => 'date received', 'tds_amount' => 'TDS', 'utr' => 'UTR / reference'];
    }
}
