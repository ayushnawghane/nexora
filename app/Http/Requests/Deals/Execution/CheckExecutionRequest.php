<?php

namespace App\Http\Requests\Deals\Execution;

use App\Http\Requests\Deals\Documents\DocumentsRequest;
use Illuminate\Validation\Rule;

class CheckExecutionRequest extends DocumentsRequest
{
    protected string $ability = 'verifyExecution';

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
