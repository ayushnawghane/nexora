<?php

namespace App\Http\Requests\GodMode;

/** The body is sanitised by CorrectEngagementLetter before it is stored. */
class LetterWordingRequest extends GodModeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + ['body' => ['required', 'string', 'max:500000']];
    }
}
