<?php

namespace App\Http\Requests\Deals;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;

class JobSheetSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Transaction $deal */
        $deal = $this->route('transaction');

        return $this->user()->can('makeJobSheet', $deal);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'received_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['received_on.before_or_equal' => 'The received date can\'t be in the future.'];
    }

    public function attributes(): array
    {
        return ['received_on' => 'received date'];
    }
}
