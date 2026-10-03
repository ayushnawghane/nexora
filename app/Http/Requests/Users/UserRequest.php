<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Create and update share the same rules; uniqueness ignores the user being edited. */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return $user ? $this->user()->can('update', $user) : $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'emp_code' => Str::upper(trim((string) $this->input('emp_code'))),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'name' => trim((string) $this->input('name')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'emp_code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('users', 'emp_code')->ignore($user?->id)],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'mobile' => ['nullable', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'designation_id' => ['nullable', 'integer', Rule::exists('designations', 'id')->whereNull('deleted_at')],
            'reporting_manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at'), Rule::notIn(array_filter([$user?->id]))],
            'date_of_joining' => ['nullable', 'date', 'before_or_equal:today'],
            'is_authorised_signatory' => ['boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name'), $this->superAdminRoleRule()],
            'vertical_ids' => ['array'],
            'vertical_ids.*' => ['integer', 'distinct', Rule::exists('verticals', 'id')->whereNull('deleted_at')],
            'vertical_team_ids' => ['array'],
            'vertical_team_ids.*' => ['integer', 'distinct', Rule::exists('vertical_teams', 'id')->whereNull('deleted_at')],
            'product_ids' => ['array'],
            'product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'emp_code.regex' => 'Employee code may only contain letters, numbers and hyphens.',
            'mobile.regex' => 'Enter a valid mobile number (10–15 digits, optional +).',
            'roles.required' => 'Give the user at least one role.',
            'reporting_manager_id.not_in' => 'A user cannot report to themselves.',
        ];
    }

    /** Only a super-admin can hand out the super-admin role. */
    private function superAdminRoleRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === Permissions::superAdminRole() && ! $this->user()->hasRole(Permissions::superAdminRole())) {
                $fail('Only a super-admin can assign the super-admin role.');
            }
        };
    }
}
