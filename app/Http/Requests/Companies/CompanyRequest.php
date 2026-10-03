<?php

namespace App\Http\Requests\Companies;

use App\Enums\CompanyCategory;
use App\Enums\CompanyClass;
use App\Enums\EntityType;
use App\Models\Company;
use App\Support\IndianIdentifiers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create and update share these rules. For companies the class and listed flag come from the CIN
 * (see SaveCompany), so they're only taken from input for other entity types.
 */
class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Company|null $company */
        $company = $this->route('company');

        return $company ? $this->user()->can('update', $company) : $this->user()->can('create', Company::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cin' => IndianIdentifiers::normalise($this->input('cin')),
            'pan' => IndianIdentifiers::normalise($this->input('pan')),
            'name' => trim((string) $this->input('name')),
            'formerly_known_as' => trim((string) $this->input('formerly_known_as')) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Company|null $company */
        $company = $this->route('company');
        $type = EntityType::tryFrom((string) $this->input('entity_type'));

        return [
            'entity_type' => ['required', Rule::enum(EntityType::class)],
            'cin' => [
                Rule::requiredIf($type !== EntityType::Other),
                Rule::prohibitedIf($type === EntityType::Other),
                'nullable', 'string',
                $type === EntityType::Llp ? 'regex:'.IndianIdentifiers::LLPIN_PATTERN : 'regex:'.IndianIdentifiers::CIN_PATTERN,
                Rule::unique('companies', 'cin')->ignore($company?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'formerly_known_as' => ['nullable', 'string', 'max:255', 'different:name'],
            'pan' => [
                Rule::requiredIf($type !== EntityType::Other),
                'nullable', 'string', 'regex:'.IndianIdentifiers::PAN_PATTERN,
                Rule::unique('companies', 'pan')->ignore($company?->id),
            ],
            'company_class' => ['nullable', Rule::enum(CompanyClass::class)],
            'category' => ['nullable', Rule::enum(CompanyCategory::class)],
            'incorporated_on' => ['nullable', 'date', 'before_or_equal:today', 'after:1850-01-01'],
            'is_listed' => ['boolean'],
        ];
    }

    /**
     * @return list<\Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = EntityType::from($this->input('entity_type'));
            $pan = $this->input('pan');
            $cin = $this->input('cin');

            $allowed = $type->panHolderTypes();
            if ($pan && $allowed !== [] && ! in_array(IndianIdentifiers::panHolderType($pan), $allowed, true)) {
                $validator->errors()->add('pan', $type === EntityType::Company
                    ? 'A company PAN has "C" as its 4th character. Check the PAN or the entity type.'
                    : 'An LLP PAN has "F" or "E" as its 4th character. Check the PAN or the entity type.');
            }

            if ($type === EntityType::Company && $cin && $this->filled('incorporated_on')
                && (int) date('Y', (int) strtotime($this->input('incorporated_on'))) !== IndianIdentifiers::cinYear($cin)) {
                $validator->errors()->add('incorporated_on', 'The incorporation year must match the year in the CIN ('.IndianIdentifiers::cinYear($cin).').');
            }

            /** @var Company|null $company */
            $company = $this->route('company');
            if ($company && $pan !== $company->pan && $company->gstins()->withTrashed()
                ->when($pan, fn ($gstins) => $gstins->where('gstin', 'not like', '__'.$pan.'%'))
                ->exists()) {
                $validator->errors()->add('pan', 'This company has GSTINs issued under a different PAN. Correct them before changing the PAN.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'cin.required' => $this->input('entity_type') === EntityType::Llp->value ? 'Enter the LLPIN.' : 'Enter the CIN.',
            'cin.prohibited' => 'Other entities have no CIN or LLPIN. Change the entity type or clear the number.',
            'cin.regex' => $this->input('entity_type') === EntityType::Llp->value
                ? 'An LLPIN looks like AAB-1234.'
                : 'A CIN is 21 characters, like U65990MH2010PTC123456.',
            'cin.unique' => 'Another company already has this number.',
            'pan.regex' => 'A PAN is 10 characters, like AAACB1234C.',
            'pan.unique' => 'Another company already has this PAN.',
            'formerly_known_as.different' => 'The former name must differ from the current name.',
        ];
    }
}
