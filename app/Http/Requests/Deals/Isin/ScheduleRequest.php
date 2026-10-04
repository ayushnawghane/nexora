<?php

namespace App\Http\Requests\Deals\Isin;

use App\Enums\IsinPaymentKind;
use App\Enums\PaymentFrequency;
use App\Http\Requests\Deals\Documents\DocumentsRequest;
use Illuminate\Validation\Rule;

/** Adding due dates: generated from a frequency, from a schedule file, or a single date. */
class ScheduleRequest extends DocumentsRequest
{
    protected string $ability = 'manageIsin';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $date = ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'];

        return [
            'source' => ['required', Rule::in(['generate', 'file', 'single'])],
            'kind' => ['exclude_if:source,file', 'required', Rule::enum(IsinPaymentKind::class)],
            'first_due_on' => ['exclude_unless:source,generate', ...$date],
            'frequency' => ['exclude_unless:source,generate', 'required', Rule::enum(PaymentFrequency::class)],
            'due_on' => ['exclude_unless:source,single', ...$date],
            'file' => ['exclude_unless:source,file', 'required', 'file', 'mimes:csv,txt', 'max:1024'],
        ];
    }

    public function messages(): array
    {
        return ['file.mimes' => 'Upload the schedule as a CSV file.'];
    }

    public function attributes(): array
    {
        return ['first_due_on' => 'first due date', 'due_on' => 'due date'];
    }
}
