<?php

namespace App\Http\Requests\Transactions;

use App\Enums\Instrument;
use App\Enums\IssueType;
use App\Enums\Listing;
use App\Models\Transaction;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Step 3: issue details. The split across instruments must add up exactly to the base issue and
 * the green shoe; the total issue size is always base + green shoe (never typed in).
 */
class TransactionIssueRequest extends FormRequest
{
    private const MAX_AMOUNT = '9999999999999999.99';

    public function authorize(): bool
    {
        /** @var Transaction $transaction */
        $transaction = $this->route('transaction');

        return $this->user()->can('update', $transaction);
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => is_string($v) ? str_replace([',', ' '], '', $v) : $v;

        $this->merge([
            'base_issue_size' => $clean($this->input('base_issue_size')),
            'green_shoe_size' => $clean($this->input('green_shoe_size')) ?: '0',
            'tenure_days' => $this->input('tenure_days') ?: 0,
            'instruments' => collect($this->input('instruments', []))->map(fn ($row) => [
                'instrument' => $row['instrument'] ?? null,
                'base_amount' => $clean($row['base_amount'] ?? null) ?: '0',
                'green_shoe_amount' => $clean($row['green_shoe_amount'] ?? null) ?: '0',
            ])->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $amount = ['decimal:0,2', 'min:0', 'max:'.self::MAX_AMOUNT];

        return [
            'listing' => ['required', Rule::enum(Listing::class)],
            'issue_type' => ['required', Rule::enum(IssueType::class)],
            'is_secured' => ['required', 'boolean'],
            'is_rated' => ['required', 'boolean'],
            'base_issue_size' => ['required', ...$amount, 'gt:0'],
            'green_shoe_size' => ['required', ...$amount],
            'tenure_months' => ['required', 'integer', 'min:0', 'max:600'],
            'tenure_days' => ['required', 'integer', 'min:0', 'max:30'],
            'instruments' => ['required', 'array', 'min:1', 'max:'.count(Instrument::cases())],
            'instruments.*.instrument' => ['required', 'distinct', Rule::enum(Instrument::class)],
            'instruments.*.base_amount' => ['required', ...$amount],
            'instruments.*.green_shoe_amount' => ['required', ...$amount],
        ];
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

            if ((int) $this->input('tenure_months') === 0 && (int) $this->input('tenure_days') === 0) {
                $validator->errors()->add('tenure_months', 'Enter the tenure.');
            }

            $rows = collect($this->input('instruments'));
            $base = $rows->reduce(fn (BigDecimal $sum, $r) => $sum->plus($r['base_amount']), BigDecimal::zero());
            $green = $rows->reduce(fn (BigDecimal $sum, $r) => $sum->plus($r['green_shoe_amount']), BigDecimal::zero());

            if (! $base->isEqualTo($this->input('base_issue_size'))) {
                $validator->errors()->add('instruments', "The instrument amounts add up to {$base->toScale(2)}, not the base issue size.");
            }
            if (! $green->isEqualTo($this->input('green_shoe_size'))) {
                $validator->errors()->add('instruments', "The instrument green shoe amounts add up to {$green->toScale(2)}, not the green shoe size.");
            }
        }];
    }

    public function messages(): array
    {
        return [
            'base_issue_size.gt' => 'The base issue size must be more than zero.',
            'instruments.required' => 'Add at least one instrument.',
            'instruments.*.instrument.distinct' => 'Each instrument can only appear once.',
        ];
    }

    public function attributes(): array
    {
        return ['base_issue_size' => 'base issue size', 'green_shoe_size' => 'green shoe size', 'tenure_months' => 'tenure'];
    }
}
