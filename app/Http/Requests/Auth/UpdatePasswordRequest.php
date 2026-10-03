<?php

namespace App\Http\Requests\Auth;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::defaults(),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && Hash::check($value, (string) $this->user()?->getAuthPassword())) {
                        $fail('The new password must be different from your current password.');
                    }
                },
            ],
        ];
    }
}
