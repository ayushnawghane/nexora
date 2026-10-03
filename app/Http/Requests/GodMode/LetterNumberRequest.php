<?php

namespace App\Http\Requests\GodMode;

class LetterNumberRequest extends GodModeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'number_mode' => ['required', 'in:keep,next,manual'],
            'el_number' => ['exclude_unless:number_mode,manual', 'required', 'string', 'max:40'],
            'el_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + ['el_date.before_or_equal' => 'The EL date can\'t be in the future.'];
    }
}
