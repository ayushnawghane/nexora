<?php

namespace App\Http\Requests\Deals\Isin;

use App\Enums\IsinPaymentStatus;
use App\Enums\RedemptionBasis;
use App\Http\Requests\Deals\Documents\DocumentsRequest;
use App\Models\DocumentFile;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends DocumentsRequest
{
    protected string $ability = 'manageIsin';

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->except('files'))->map(fn ($v) => $v === '' ? null : (is_string($v) ? trim($v) : $v))->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:9999999999999999', 'decimal:0,2'];

        return [
            'status' => ['required', Rule::in([IsinPaymentStatus::Paid->value, IsinPaymentStatus::Defaulted->value, IsinPaymentStatus::RedeemedEarly->value])],
            'paid_on' => ['nullable', 'required_if:status,paid', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'amount' => [...$money, 'required_if:status,paid'],
            'redemption_basis' => ['nullable', Rule::enum(RedemptionBasis::class)],
            'face_value' => $money,
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'remark' => ['nullable', 'string', 'max:2000'],
            'files' => ['array', 'max:'.DocumentFile::MAX_FILES],
            'files.*' => DocumentFile::RULES,
        ];
    }

    public function messages(): array
    {
        return [
            'paid_on.required_if' => 'Enter the date it was paid.',
            'paid_on.before_or_equal' => 'The payment date can\'t be in the future.',
            'amount.required_if' => 'Enter the amount paid.',
        ];
    }

    public function attributes(): array
    {
        return ['paid_on' => 'date paid', 'redemption_basis' => 'redemption'];
    }
}
