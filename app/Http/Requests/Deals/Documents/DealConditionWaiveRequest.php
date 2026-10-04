<?php

namespace App\Http\Requests\Deals\Documents;

class DealConditionWaiveRequest extends DocumentsRequest
{
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
        return ['reason' => ['required', 'string', 'min:5', 'max:2000']];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Say why this doesn\'t apply to the deal.'];
    }
}
