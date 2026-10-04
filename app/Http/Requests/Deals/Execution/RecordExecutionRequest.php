<?php

namespace App\Http\Requests\Deals\Execution;

use App\Http\Requests\Deals\Documents\DocumentsRequest;

class RecordExecutionRequest extends DocumentsRequest
{
    protected string $ability = 'manageExecution';

    protected function prepareForValidation(): void
    {
        foreach (['document_date', 'comments'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The executed copy is the signed document itself, so PDF only (as in Stack).
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'executed_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'document_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:executed_on'],
            'comments' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Upload the executed copy as a PDF.',
            'file.max' => 'The file must be 20 MB or smaller.',
            'executed_on.before_or_equal' => 'The execution date can\'t be in the future.',
            'document_date.before_or_equal' => 'The document date can\'t be after the execution date.',
        ];
    }

    public function attributes(): array
    {
        return ['executed_on' => 'execution date', 'document_date' => 'document date', 'file' => 'executed copy'];
    }
}
