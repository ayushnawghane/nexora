<?php

namespace App\Http\Requests\Deals\Isin;

use App\Enums\AllotmentKind;
use App\Http\Requests\Deals\Documents\DocumentsRequest;
use App\Models\DocumentFile;
use Illuminate\Validation\Rule;

class AllotmentRequest extends DocumentsRequest
{
    protected string $ability = 'manageIsin';

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->except('file'))->map(fn ($v) => $v === '' ? null : $v)->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $date = ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'];

        return [
            'kind' => ['required', Rule::enum(AllotmentKind::class)],
            'allotment_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'issue_opened_on' => [...$date, 'before_or_equal:allotment_date'],
            'issue_closed_on' => [...$date, 'after_or_equal:issue_opened_on', 'before_or_equal:allotment_date'],
            'face_value' => ['required', 'numeric', 'gt:0', 'max:9999999999999999', 'decimal:0,2'],
            'quantity_offered' => ['nullable', 'integer', 'min:1'],
            'quantity_allotted' => ['required', 'integer', 'min:1'],
            'credit_depository' => ['nullable', Rule::in(['NSDL', 'CDSL'])],
            'credited_on' => [...$date, 'after_or_equal:allotment_date'],
            'file' => ['nullable', ...DocumentFile::RULES],
        ];
    }

    public function messages(): array
    {
        return [
            'allotment_date.before_or_equal' => 'The allotment date can\'t be in the future.',
            'issue_closed_on.before_or_equal' => 'The issue must close on or before the allotment date.',
        ];
    }
}
