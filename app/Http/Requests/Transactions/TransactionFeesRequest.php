<?php

namespace App\Http\Requests\Transactions;

use App\Enums\EscalationType;
use App\Enums\FeeAmountType;
use App\Enums\FeeBasis;
use App\Enums\FeeFrequency;
use App\Enums\FeeKind;
use App\Enums\FeeStartReference;
use App\Enums\FeeTiming;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Step 4: the acceptance fee (one time) and the service fee (per annum, recurring). Each can be
 * switched off, but a transaction needs at least one fee. A percentage is always of the issue size.
 */
class TransactionFeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->transaction());
    }

    protected function prepareForValidation(): void
    {
        $fees = [];
        foreach (FeeKind::cases() as $kind) {
            $fee = (array) $this->input("fees.{$kind->value}", []);
            $fees[$kind->value] = [
                ...$fee,
                'enabled' => filter_var($fee['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'amount' => isset($fee['amount']) ? (str_replace([',', ' '], '', (string) $fee['amount']) ?: null) : null,
                'escalation_type' => $fee['escalation_type'] ?? EscalationType::None->value,
            ];
        }
        $this->merge(['fees' => $fees]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['fees' => ['required', 'array']];

        foreach (FeeKind::cases() as $kind) {
            $p = "fees.{$kind->value}";
            $on = "required_if_accepted:{$p}.enabled";
            $frequencies = array_map(fn (FeeFrequency $f) => $f->value, $kind->allowedFrequencies());

            $rules += [
                "{$p}.enabled" => ['boolean'],
                "{$p}.amount_type" => [$on, 'nullable', Rule::enum(FeeAmountType::class)],
                "{$p}.amount" => ["required_if:{$p}.amount_type,fixed", 'nullable', 'decimal:0,2', 'gt:0', 'max:9999999999999999.99'],
                "{$p}.percent" => ["required_if:{$p}.amount_type,percent", 'nullable', 'decimal:0,6', 'gt:0', 'max:100'],
                "{$p}.basis" => [$on, 'nullable', Rule::enum(FeeBasis::class)],
                "{$p}.frequency" => [$on, 'nullable', Rule::in($frequencies)],
                "{$p}.start_reference" => [$on, 'nullable', Rule::enum(FeeStartReference::class)],
                "{$p}.start_date" => [$on, 'nullable', 'date', 'after:2000-01-01', 'before:2100-01-01'],
                "{$p}.timing" => [$on, 'nullable', Rule::enum(FeeTiming::class)],
                "{$p}.escalation_type" => ['required', $kind === FeeKind::Service ? Rule::enum(EscalationType::class) : Rule::in([EscalationType::None->value])],
                "{$p}.escalation_value" => ["exclude_if:{$p}.escalation_type,none", 'required', 'decimal:0,2', 'gt:0', 'max:9999999999999999.99'],
                "{$p}.escalation_every_years" => ["exclude_if:{$p}.escalation_type,none", 'required', 'integer', 'min:1', 'max:30'],
            ];
        }

        return $rules;
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

            $enabled = collect($this->input('fees'))->filter(fn ($fee) => $fee['enabled']);
            if ($enabled->isEmpty()) {
                $validator->errors()->add('fees', 'Add at least one fee.');
            }

            foreach ($enabled as $kind => $fee) {
                if ($fee['amount_type'] === FeeAmountType::Percent->value
                    && ! in_array($fee['basis'], [FeeBasis::IssueSize->value, FeeBasis::IssueSizeTranches->value], true)) {
                    $validator->errors()->add("fees.{$kind}.basis", 'A percentage fee must be of the issue size.');
                }
                if (($fee['escalation_type'] ?? 'none') === EscalationType::Percent->value && (float) $fee['escalation_value'] > 100) {
                    $validator->errors()->add("fees.{$kind}.escalation_value", 'A percentage escalation can\'t exceed 100%.');
                }
            }

            if ($this->transaction()->issueDetail()->doesntExist()) {
                $validator->errors()->add('fees', 'Save the issue details first; the schedule runs for the issue\'s tenure.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'fees.*.*.required_if_accepted' => 'This field is required.',
            'fees.*.amount.required_if' => 'Enter the fee amount.',
            'fees.*.percent.required_if' => 'Enter the percentage.',
            'fees.*.frequency.in' => 'Pick a frequency this fee allows.',
            'fees.*.escalation_type.in' => 'The acceptance fee has no escalation.',
        ];
    }

    public function transaction(): Transaction
    {
        /** @var Transaction */
        return $this->route('transaction');
    }
}
