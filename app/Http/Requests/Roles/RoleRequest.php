<?php

namespace App\Http\Requests\Roles;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Role|null $role */
        $role = $this->route('role');

        return $role ? $this->user()->can('update', $role) : $this->user()->can('create', Role::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => Str::of((string) $this->input('name'))->trim()->lower()->replaceMatches('/\s+/', '-')->toString()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Role|null $role */
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:60', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('roles', 'name')->ignore($role?->id),
                Rule::notIn([Permissions::superAdminRole()]),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'distinct', Rule::in(Permissions::all())],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Use letters, numbers and hyphens only.',
            'name.not_in' => 'That name is reserved.',
        ];
    }
}
