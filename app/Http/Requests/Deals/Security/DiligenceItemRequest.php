<?php

namespace App\Http\Requests\Deals\Security;

use App\Enums\DiligenceKind;
use App\Http\Requests\Deals\Documents\DocumentsRequest;
use Illuminate\Validation\Rule;

class DiligenceItemRequest extends DocumentsRequest
{
    protected string $ability = 'manageSecurity';

    protected function prepareForValidation(): void
    {
        $clean = [];
        foreach (['title', 'asset_owner', 'reference'] as $field) {
            if (is_string($this->input($field))) {
                $clean[$field] = trim((string) preg_replace('/\s+/u', ' ', $this->input($field))) ?: null;
            }
        }
        foreach (['deal_security_id', 'empanelled_agency_id'] as $field) {
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
            'kind' => ['required', Rule::enum(DiligenceKind::class)],
            'title' => ['required', 'string', 'max:500'],
            'deal_security_id' => ['nullable', 'integer'],
            'asset_owner' => ['nullable', 'string', 'max:255'],
            'empanelled_agency_id' => ['nullable', 'integer', Rule::exists('empanelled_agencies', 'id')->whereNull('deleted_at')],
            'reference' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function attributes(): array
    {
        return ['deal_security_id' => 'security', 'empanelled_agency_id' => 'empanelled agency', 'reference' => 'UDIN / reference'];
    }
}
