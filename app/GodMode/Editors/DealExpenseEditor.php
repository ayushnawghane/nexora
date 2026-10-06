<?php

namespace App\GodMode\Editors;

use App\GodMode\Editor;
use App\Models\DealExpense;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Corrects an out-of-pocket expense, including a billed one. A billed expense's amount stays as
 * billed: correct the invoice line instead, so the two never disagree.
 *
 * @extends Editor<DealExpense>
 */
class DealExpenseEditor extends Editor
{
    public function key(): string
    {
        return 'deal-expense';
    }

    public function label(): string
    {
        return 'Expense';
    }

    public function modelClass(): string
    {
        return DealExpense::class;
    }

    public function values(Model $record): array
    {
        return [
            'incurred_on' => $record->incurred_on?->toDateString(),
            'description' => $record->description,
            'amount' => $record->amount,
        ];
    }

    public function fields(Model $record): array
    {
        return [
            ['name' => 'incurred_on', 'label' => 'Date', 'type' => 'date'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'text', 'required' => true],
            ['name' => 'amount', 'label' => 'Amount (₹)', 'type' => 'number', 'required' => true],
        ];
    }

    public function validate(Model $record, array $input, User $actor): array
    {
        $input = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $input);
        $validated = array_merge(['incurred_on' => null], Validator::make($input, [
            'incurred_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999', 'decimal:0,2'],
        ])->validate());

        if ($record->invoice_id !== null && ! BigDecimal::of((string) $validated['amount'])->isEqualTo($record->amount)) {
            throw ValidationException::withMessages(['amount' => 'This expense is on an invoice: correct the invoice line\'s amount instead.']);
        }

        return $validated;
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        $record->update($validated);
    }

    public function transactionId(Model $record): int
    {
        return $record->transaction_id;
    }
}
