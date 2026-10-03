<?php

namespace App\Http\Requests\Companies;

use App\Models\Company;
use App\Models\CompanyContact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** A contact needs a way to be reached: an email or a mobile at least. Emails are unique within a company. */
class CompanyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->company());
    }

    protected function prepareForValidation(): void
    {
        $trim = fn (string $key) => trim((string) $this->input($key)) ?: null;

        $this->merge([
            'name' => $trim('name'),
            'designation' => $trim('designation'),
            'department' => $trim('department'),
            'email' => Str::lower((string) $trim('email')) ?: null,
            'mobile' => preg_replace('/[\s\-]+/', '', (string) $this->input('mobile')) ?: null,
            'landline' => $trim('landline'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contact_type_id' => ['nullable', 'integer', Rule::exists('contact_types', 'id')
                ->whereNull('deleted_at')
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $this->editing()->contact_type_id ?? 0))],
            'salutation' => ['nullable', Rule::in(CompanyContact::SALUTATIONS)],
            'name' => ['required', 'string', 'max:150'],
            'designation' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'required_without:mobile', 'email:rfc', 'max:255',
                Rule::unique('company_contacts', 'email')->where('company_id', $this->company()->id)->ignore($this->editing()?->id)],
            'mobile' => ['nullable', 'required_without:email', 'regex:/^\+?[0-9]{10,15}$/'],
            'landline' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-() ]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => 'Give an email or a mobile number.',
            'mobile.required_without' => 'Give an email or a mobile number.',
            'email.unique' => 'This company already has a contact with this email.',
            'mobile.regex' => 'Enter a valid mobile number (10–15 digits, optional +).',
            'landline.regex' => 'A landline may only contain digits, spaces, +, - and brackets.',
            'contact_type_id.exists' => 'Pick an active contact type.',
        ];
    }

    public function attributes(): array
    {
        return ['contact_type_id' => 'contact type'];
    }

    public function company(): Company
    {
        /** @var Company */
        return $this->route('company');
    }

    public function editing(): ?CompanyContact
    {
        /** @var CompanyContact|null */
        return $this->route('contact');
    }
}
