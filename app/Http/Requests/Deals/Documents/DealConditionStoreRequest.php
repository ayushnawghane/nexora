<?php

namespace App\Http\Requests\Deals\Documents;

use App\Enums\ConditionStage;
use Illuminate\Validation\Rule;

/** Adds CP/CS items: chosen from the master list, or one written for this deal. */
class DealConditionStoreRequest extends DocumentsRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim((string) preg_replace('/\s+/u', ' ', $this->input('name'))) ?: null]);
        }
        if ($this->input('due_on') === '') {
            $this->merge(['due_on' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::enum(ConditionStage::class)],
            'source' => ['required', Rule::in(['master', 'custom'])],
            'document_ids' => ['exclude_unless:source,master', 'required', 'array', 'min:1', 'max:200'],
            'document_ids.*' => ['integer', 'distinct'],
            'name' => ['exclude_unless:source,custom', 'required', 'string', 'max:2000'],
            'issuing_authority_id' => ['exclude_unless:source,custom', 'nullable', 'integer', Rule::exists('issuing_authorities', 'id')->whereNull('deleted_at')],
            'due_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
        ];
    }

    public function messages(): array
    {
        return ['document_ids.required' => 'Choose at least one document.'];
    }

    public function attributes(): array
    {
        return ['document_ids' => 'documents', 'issuing_authority_id' => 'issuing authority', 'due_on' => 'due date'];
    }
}
