<?php

namespace App\Http\Requests\Deals\Documents;

class DealConditionDueDateRequest extends DocumentsRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('due_on') === '') {
            $this->merge(['due_on' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['due_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31']];
    }

    public function attributes(): array
    {
        return ['due_on' => 'due date'];
    }
}
