<?php

namespace App\Http\Requests\Deals\Execution;

use App\Enums\SignatoryType;
use App\Http\Requests\Deals\Documents\DocumentsRequest;
use Illuminate\Validation\Rule;

class ScheduleExecutionRequest extends DocumentsRequest
{
    protected string $ability = 'manageExecution';

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('place'))) {
            $this->merge(['place' => trim((string) preg_replace('/\s+/u', ' ', $this->input('place')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'execution_ids' => ['required', 'array', 'min:1', 'max:100'],
            'execution_ids.*' => ['integer', 'distinct'],
            'place' => ['required', 'string', 'max:100'],
            'scheduled_at' => ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'signatory_type' => ['required', Rule::enum(SignatoryType::class)],
            'signatory_user_id' => ['exclude_unless:signatory_type,internal', 'required', 'integer', Rule::exists('users', 'id')],
            'poa_holder_id' => ['exclude_unless:signatory_type,external', 'required', 'integer', Rule::exists('poa_holders', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'execution_ids.required' => 'Choose at least one document.',
            'scheduled_at.date_format' => 'Enter the execution date and time.',
        ];
    }

    public function attributes(): array
    {
        return ['scheduled_at' => 'date and time', 'signatory_user_id' => 'signatory', 'poa_holder_id' => 'POA holder'];
    }
}
