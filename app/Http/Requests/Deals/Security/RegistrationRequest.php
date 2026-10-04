<?php

namespace App\Http\Requests\Deals\Security;

use App\Enums\RegistrationAction;
use App\Enums\RegistrationKind;
use App\Http\Requests\Deals\Documents\DocumentsRequest;
use App\Models\DocumentFile;
use Illuminate\Validation\Rule;

/**
 * A new registration (route without {registration}) or a modification / satisfaction of one.
 */
class RegistrationRequest extends DocumentsRequest
{
    protected function prepareForValidation(): void
    {
        $this->ability = $this->route('registration') ? 'satisfyRegistration' : 'manageSecurity';

        $clean = [];
        foreach (['reference', 'filing_reference', 'security_name', 'depository', 'pledgor_dp_id', 'pledgor_client_id', 'pledgee_dp_id', 'pledgee_client_id', 'reason'] as $field) {
            if (is_string($this->input($field))) {
                $clean[$field] = trim($this->input($field)) ?: null;
            }
        }
        foreach (['amount', 'quantity', 'face_value'] as $field) {
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
        $creating = $this->route('registration') === null;
        $pledge = $this->input('kind') === RegistrationKind::Pledge->value;

        return [
            'kind' => [$creating ? 'required' : 'exclude', Rule::enum(RegistrationKind::class)],
            'action' => [$creating ? 'exclude' : 'required', Rule::in([RegistrationAction::Modify->value, RegistrationAction::Satisfy->value])],
            'security_ids' => [$creating ? 'required' : 'exclude', 'array', 'min:1', 'max:100'],
            'security_ids.*' => ['integer', 'distinct'],
            'happened_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'filing_reference' => ['nullable', 'string', 'max:60'],
            'reference' => [$creating ? 'nullable' : 'exclude', 'string', 'max:60'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999', 'decimal:0,2'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'security_name' => [$creating && $pledge ? 'required' : 'exclude', 'string', 'max:255'],
            'quantity' => [$creating && $pledge ? 'required' : 'exclude', 'integer', 'min:1'],
            'face_value' => [$creating && $pledge ? 'nullable' : 'exclude', 'numeric', 'min:0', 'max:9999999999999999', 'decimal:0,2'],
            'depository' => [$creating && $pledge ? 'required' : 'exclude', Rule::in(['NSDL', 'CDSL'])],
            'pledgor_dp_id' => [$creating && $pledge ? 'nullable' : 'exclude', 'string', 'max:20'],
            'pledgor_client_id' => [$creating && $pledge ? 'nullable' : 'exclude', 'string', 'max:20'],
            'pledgee_dp_id' => [$creating && $pledge ? 'nullable' : 'exclude', 'string', 'max:20'],
            'pledgee_client_id' => [$creating && $pledge ? 'nullable' : 'exclude', 'string', 'max:20'],
            'files' => ['array', 'max:'.DocumentFile::MAX_FILES],
            'files.*' => DocumentFile::RULES,
        ];
    }

    public function messages(): array
    {
        return [
            'security_ids.required' => 'Choose the securities this registration covers.',
            'happened_on.before_or_equal' => 'The date can\'t be in the future.',
            'files.*.mimes' => 'Upload PDF, Word, Excel or image files only.',
            'files.*.max' => 'Each file must be 20 MB or smaller.',
        ];
    }

    public function attributes(): array
    {
        return ['happened_on' => 'date', 'filing_reference' => 'filing number', 'security_ids' => 'securities', 'security_name' => 'security'];
    }
}
