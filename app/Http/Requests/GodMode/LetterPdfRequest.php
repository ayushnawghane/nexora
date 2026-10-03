<?php

namespace App\Http\Requests\GodMode;

class LetterPdfRequest extends GodModeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + ['pdf' => ['required', 'file', 'mimes:pdf', 'max:10240']];
    }

    public function messages(): array
    {
        return parent::messages() + ['pdf.max' => 'The PDF must be 10 MB or smaller.'];
    }
}
