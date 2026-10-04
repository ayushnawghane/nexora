<?php

namespace App\Http\Requests\Deals\Documents;

use Illuminate\Validation\Rule;

class DealConditionCheckRequest extends DocumentsRequest
{
    protected string $ability = 'verifyDocuments';

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
