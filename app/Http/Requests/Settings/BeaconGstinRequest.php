<?php

namespace App\Http\Requests\Settings;

use App\Models\State;
use App\Support\IndianIdentifiers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BeaconGstinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['gstin' => IndianIdentifiers::normalise($this->input('gstin'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['gstin' => ['required', 'string', 'size:15', 'regex:'.IndianIdentifiers::GSTIN_PATTERN]];
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

            $gstin = (string) $this->input('gstin');
            if (! IndianIdentifiers::isGstin($gstin)) {
                $validator->errors()->add('gstin', 'This GSTIN fails its check-digit test. Check it for typing mistakes.');
            } elseif (! State::query()->where('gst_code', IndianIdentifiers::gstinStateCode($gstin))->exists()) {
                $validator->errors()->add('gstin', 'The first two digits are not a GST state code.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'gstin.size' => 'A GSTIN is 15 characters.',
            'gstin.regex' => 'A GSTIN looks like 27AAACB1234C1Z5.',
        ];
    }
}
