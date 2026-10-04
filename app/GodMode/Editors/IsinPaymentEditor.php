<?php

namespace App\GodMode\Editors;

use App\Enums\IsinPaymentKind;
use App\Enums\IsinPaymentStatus;
use App\Enums\RedemptionBasis;
use App\GodMode\Editor;
use App\GodMode\Options;
use App\Models\IsinPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Corrects a scheduled payment, including one already recorded (the normal screen locks those):
 * its due date, outcome, date paid and amounts. Its files and reminders stay as they are.
 *
 * @extends Editor<IsinPayment>
 */
class IsinPaymentEditor extends Editor
{
    public function key(): string
    {
        return 'isin-payment';
    }

    public function label(): string
    {
        return 'ISIN payment';
    }

    public function modelClass(): string
    {
        return IsinPayment::class;
    }

    public function values(Model $record): array
    {
        return [
            'due_on' => $record->due_on->toDateString(),
            'status' => $record->status->value,
            'paid_on' => $record->paid_on?->toDateString(),
            'amount' => $record->amount,
            'redemption_basis' => $record->redemption_basis?->value,
            'face_value' => $record->face_value,
            'quantity' => $record->quantity,
            'remark' => $record->remark,
        ];
    }

    public function fields(Model $record): array
    {
        $principal = $record->kind === IsinPaymentKind::Principal;

        return array_values(array_filter([
            ['name' => 'due_on', 'label' => 'Due date', 'type' => 'date', 'required' => true],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => Options::enum(IsinPaymentStatus::class)],
            ['name' => 'paid_on', 'label' => 'Paid on', 'type' => 'date'],
            ['name' => 'amount', 'label' => 'Amount paid (₹)', 'type' => 'number'],
            $principal ? ['name' => 'redemption_basis', 'label' => 'Redemption', 'type' => 'select', 'options' => Options::enum(RedemptionBasis::class)] : null,
            $principal ? ['name' => 'face_value', 'label' => 'Face value redeemed (₹)', 'type' => 'number'] : null,
            $principal ? ['name' => 'quantity', 'label' => 'Quantity redeemed', 'type' => 'number'] : null,
            ['name' => 'remark', 'label' => 'Remark', 'type' => 'textarea'],
        ]));
    }

    /**
     * The limits the payment form applies, plus: a paid payment has its date and amount; the due
     * date stays unique in its schedule.
     */
    public function validate(Model $record, array $input, User $actor): array
    {
        $input = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $input);
        $money = ['nullable', 'numeric', 'min:0', 'max:9999999999999999', 'decimal:0,2'];

        return Validator::make($input, [
            'due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31',
                Rule::unique('isin_payments', 'due_on')->where('deal_isin_id', $record->deal_isin_id)->where('kind', $record->kind->value)->ignore($record->id)],
            'status' => ['required', Rule::enum(IsinPaymentStatus::class)],
            'paid_on' => ['nullable', 'required_if:status,paid', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'amount' => [...$money, 'required_if:status,paid'],
            'redemption_basis' => ['nullable', Rule::enum(RedemptionBasis::class)],
            'face_value' => $money,
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'remark' => ['nullable', 'string', 'max:2000'],
        ], ['due_on.unique' => 'The schedule already has a payment of this kind on that date.'])->validate();
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        $due = ($validated['status'] ?? null) === IsinPaymentStatus::Due->value;
        // A moved due date keeps the first original date; moving it back (or a rollback) clears it.
        $original = $record->original_due_on ?? $record->due_on;
        $moved = $validated['due_on'] !== $original->toDateString();
        $record->update([
            'original_due_on' => $moved ? $original : null,
            'due_date_reason' => $moved ? $record->due_date_reason : null,
            'due_on' => $validated['due_on'],
            'status' => $validated['status'],
            'paid_on' => $due ? null : ($validated['paid_on'] ?? null),
            'amount' => $due ? null : ($validated['amount'] ?? null),
            'redemption_basis' => $due ? null : ($validated['redemption_basis'] ?? null),
            'face_value' => $due ? null : ($validated['face_value'] ?? null),
            'quantity' => $due ? null : ($validated['quantity'] ?? null),
            'remark' => $validated['remark'] ?? null,
        ]);
    }

    public function transactionId(Model $record): int
    {
        return (int) $record->isin()->value('transaction_id');
    }
}
