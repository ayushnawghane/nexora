<?php

namespace App\Http\Requests\Deals\Security;

use App\Enums\OwnerIdType;
use App\Enums\SecurityNature;
use App\Http\Requests\Deals\Documents\DocumentsRequest;
use App\Support\IndianIdentifiers;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DealSecurityRequest extends DocumentsRequest
{
    protected string $ability = 'manageSecurity';

    protected function prepareForValidation(): void
    {
        $clean = [];
        foreach (['asset_owner', 'pertaining_to', 'description', 'address', 'city', 'form_of_securities', 'confirming_party', 'pincode'] as $field) {
            if (is_string($this->input($field))) {
                $clean[$field] = trim((string) preg_replace('/[ \t]+/u', ' ', $this->input($field))) ?: null;
            }
        }
        if (is_string($this->input('owner_id_number'))) {
            $clean['owner_id_number'] = IndianIdentifiers::normalise($this->input('owner_id_number'));
        }
        foreach (['owner_id_type', 'asset_type_id', 'charge_type_id', 'state_id', 'is_encumbered'] as $field) {
            if ($this->input($field) === '') {
                $clean[$field] = null;
            }
        }
        $this->merge($clean);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'deal_document_id' => ['required', 'integer'],
            'nature' => ['required', Rule::enum(SecurityNature::class)],
            'asset_owner' => ['required', 'string', 'max:255'],
            'owner_id_type' => ['nullable', Rule::enum(OwnerIdType::class)],
            'owner_id_number' => ['nullable', 'required_with:owner_id_type', 'string', 'max:25'],
            'asset_type_id' => ['nullable', 'integer', Rule::exists('asset_types', 'id')->whereNull('deleted_at')],
            'charge_type_id' => ['nullable', 'integer', Rule::exists('charge_types', 'id')->whereNull('deleted_at')],
            'security_type_ids' => ['array', 'max:50'],
            'security_type_ids.*' => ['integer', 'distinct', Rule::exists('security_types', 'id')->whereNull('deleted_at')],
            'pertaining_to' => ['nullable', 'string', 'max:255'],
            'is_encumbered' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:500'],
            'pincode' => ['nullable', 'regex:/^[1-9][0-9]{5}$/'],
            'city' => ['nullable', 'string', 'max:120'],
            'state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'form_of_securities' => ['nullable', 'string', 'max:255'],
            'confirming_party' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $type = OwnerIdType::tryFrom((string) $this->input('owner_id_type'));
            $number = (string) $this->input('owner_id_number');
            if ($type === null || $number === '' || $validator->errors()->has('owner_id_number')) {
                return;
            }
            $valid = $type === OwnerIdType::Cin
                ? IndianIdentifiers::isCin($number) || IndianIdentifiers::isLlpin($number)
                : IndianIdentifiers::isPan($number);
            if (! $valid) {
                $validator->errors()->add('owner_id_number', $type === OwnerIdType::Cin ? 'Enter a valid CIN or LLPIN.' : 'Enter a valid PAN.');
            }
        }];
    }

    public function messages(): array
    {
        return ['pincode.regex' => 'Enter a 6-digit pincode.'];
    }

    public function attributes(): array
    {
        return [
            'deal_document_id' => 'legal document', 'owner_id_number' => 'CIN / PAN', 'asset_type_id' => 'asset type',
            'charge_type_id' => 'charge type', 'state_id' => 'state', 'security_type_ids' => 'security types',
        ];
    }
}
