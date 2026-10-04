<?php

namespace App\Http\Requests\Deals\Isin;

use App\Http\Requests\Deals\Documents\DocumentsRequest;

class MoveDueDateRequest extends DocumentsRequest
{
    protected string $ability = 'manageIsin';

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Say why the due date moved.'];
    }
}
