<?php

namespace App\Http\Requests\Approvals;

use App\Enums\VoteDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Who may vote is checked by CastVote; this only checks the input. */
class VoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(VoteDecision::class)],
            'comment' => ['nullable', 'required_if:decision,reject', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['comment.required_if' => 'Say why you are rejecting it.'];
    }
}
