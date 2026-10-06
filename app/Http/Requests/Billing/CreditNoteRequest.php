<?php

namespace App\Http\Requests\Billing;

/** A draft credit note: how much to reduce each line of the tax invoice, and why. */
class CreditNoteRequest extends BillingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amounts' => ['required', 'array', 'max:60'],
            'amounts.*' => ['nullable', ...self::MONEY],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['amounts.required' => 'Enter the amount to credit on at least one line.'];
    }
}
