<?php

namespace App\Http\Requests\Billing;

use App\Models\DocumentFile;

/** An out-of-pocket expense on a deal, with its proof. */
class ExpenseRequest extends BillingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'incurred_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', ...self::MONEY, 'gt:0'],
            'files' => ['array', 'max:'.DocumentFile::MAX_FILES],
            'files.*' => DocumentFile::RULES,
        ];
    }

    public function messages(): array
    {
        return [
            'incurred_on.before_or_equal' => 'The expense date can\'t be in the future.',
            'amount.gt' => 'The amount must be more than zero.',
        ];
    }

    public function attributes(): array
    {
        return ['incurred_on' => 'date'];
    }
}
