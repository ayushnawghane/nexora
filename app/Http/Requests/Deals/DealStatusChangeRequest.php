<?php

namespace App\Http\Requests\Deals;

use App\Enums\DealStatus;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Whether the move is allowed for this deal is checked by RequestDealStatusChange. */
class DealStatusChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Transaction $deal */
        $deal = $this->route('transaction');

        return $this->user()->can('requestStatus', $deal);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string) $this->input('reason'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to_status' => ['required', Rule::enum(DealStatus::class)],
            'effective_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'noc' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'effective_on.before_or_equal' => 'The effective date can\'t be in the future.',
            'noc.max' => 'The NOC must be 10 MB or smaller.',
        ];
    }

    public function attributes(): array
    {
        return ['to_status' => 'new status', 'effective_on' => 'effective date', 'noc' => 'NOC'];
    }
}
