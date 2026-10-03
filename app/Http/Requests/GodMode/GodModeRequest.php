<?php

namespace App\Http\Requests\GodMode;

use Illuminate\Foundation\Http\FormRequest;

/** Every God Mode write needs a written reason. Access itself is checked by the route (super-admin + fresh 2FA). */
class GodModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('use-god-mode');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string) $this->input('reason'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:2000']];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Say why this correction is needed. It is kept with the change.',
            'reason.min' => 'Give a little more detail (at least 10 characters).',
        ];
    }
}
