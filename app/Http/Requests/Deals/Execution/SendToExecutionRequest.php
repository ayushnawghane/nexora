<?php

namespace App\Http\Requests\Deals\Execution;

use App\Http\Requests\Deals\Documents\DocumentsRequest;

class SendToExecutionRequest extends DocumentsRequest
{
    protected string $ability = 'manageExecution';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_ids' => ['required', 'array', 'min:1', 'max:100'],
            'document_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return ['document_ids.required' => 'Choose at least one document.'];
    }
}
