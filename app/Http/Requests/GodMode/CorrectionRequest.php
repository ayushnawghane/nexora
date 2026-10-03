<?php

namespace App\Http\Requests\GodMode;

/** The values themselves are validated by the record's own editor (the normal form's rules). */
class CorrectionRequest extends GodModeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'fingerprint' => ['required', 'string', 'size:64'],
            'values' => ['required', 'array'],
        ];
    }
}
