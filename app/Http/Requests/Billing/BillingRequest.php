<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

/** Base for the billing forms: the user needs the form's billing permission; text is trimmed. */
abstract class BillingRequest extends FormRequest
{
    protected string $permission = 'billing.raise';

    /** Money: up to 16 digits before the point and 2 after. */
    protected const MONEY = ['numeric', 'min:0', 'max:9999999999999999', 'decimal:0,2'];

    public function authorize(): bool
    {
        return $this->user()->can($this->permission);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->except('files'))->map(fn ($v) => self::clean($v))->all());
    }

    private static function clean(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => self::clean($v), $value);
        }
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }
}
