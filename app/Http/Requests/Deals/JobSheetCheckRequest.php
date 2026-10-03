<?php

namespace App\Http\Requests\Deals;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobSheetCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Transaction $deal */
        $deal = $this->route('transaction');

        return $this->user()->can('checkJobSheet', $deal);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['verified', 'returned'])],
            'comment' => ['nullable', 'required_if:decision,returned', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['comment.required_if' => 'Say what needs fixing.'];
    }
}
