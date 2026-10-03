<?php

namespace App\GodMode\Editors;

use App\Actions\Transactions\SaveFees;
use App\Enums\EscalationType;
use App\Enums\FeeAmountType;
use App\Enums\FeeBasis;
use App\Enums\FeeFrequency;
use App\Enums\FeeKind;
use App\Enums\FeeStartReference;
use App\Enums\FeeTiming;
use App\GodMode\Options;
use App\GodMode\TransactionEditor;
use App\Http\Requests\Transactions\TransactionFeesRequest;
use App\Models\FeeLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Saving rebuilds the fee schedule (as in the wizard), which then has to be verified again.
 */
class FeesEditor extends TransactionEditor
{
    public function key(): string
    {
        return 'fees';
    }

    public function label(): string
    {
        return 'Fees';
    }

    protected function request(): string
    {
        return TransactionFeesRequest::class;
    }

    public function values(Model $record): array
    {
        $lines = $record->feeLines()->get()->keyBy(fn (FeeLine $f) => $f->kind->value);
        $fees = [];

        foreach (FeeKind::cases() as $kind) {
            /** @var FeeLine|null $f */
            $f = $lines->get($kind->value);
            $fees[$kind->value] = [
                'enabled' => $f !== null,
                'amount_type' => $f?->amount_type->value,
                'amount' => $f?->amount,
                'percent' => $f?->percent,
                'basis' => $f?->basis->value,
                'frequency' => $f?->frequency->value,
                'start_reference' => $f?->start_reference->value,
                'start_date' => $f?->start_date->toDateString(),
                'timing' => $f?->timing->value,
                'escalation_type' => $f?->escalation_type->value ?? EscalationType::None->value,
                'escalation_value' => $f?->escalation_value,
                'escalation_every_years' => $f?->escalation_every_years,
            ];
        }

        return ['fees' => $fees];
    }

    public function fields(Model $record): array
    {
        return array_map(fn (FeeKind $kind) => [
            'name' => "fees.{$kind->value}", 'label' => $kind->label(), 'type' => 'group',
            'fields' => [
                ['name' => 'enabled', 'label' => 'Charged', 'type' => 'boolean'],
                ['name' => 'amount_type', 'label' => 'Amount type', 'type' => 'select', 'options' => Options::enum(FeeAmountType::class)],
                ['name' => 'amount', 'label' => 'Amount (₹)', 'type' => 'number'],
                ['name' => 'percent', 'label' => 'Percent', 'type' => 'number'],
                ['name' => 'basis', 'label' => 'Basis', 'type' => 'select', 'options' => Options::enum(FeeBasis::class)],
                ['name' => 'frequency', 'label' => 'Frequency', 'type' => 'select',
                    'options' => array_map(fn (FeeFrequency $f) => ['value' => $f->value, 'label' => $f->label()], $kind->allowedFrequencies())],
                ['name' => 'start_reference', 'label' => 'Runs from', 'type' => 'select', 'options' => Options::enum(FeeStartReference::class)],
                ['name' => 'start_date', 'label' => 'Start date', 'type' => 'date'],
                ['name' => 'timing', 'label' => 'Billed in', 'type' => 'select', 'options' => Options::enum(FeeTiming::class)],
                ['name' => 'escalation_type', 'label' => 'Escalation', 'type' => 'select', 'required' => true,
                    // Only the service fee can escalate (same rule as the fees form).
                    'options' => $kind === FeeKind::Service
                        ? Options::enum(EscalationType::class)
                        : [['value' => EscalationType::None->value, 'label' => EscalationType::None->label()]]],
                ['name' => 'escalation_value', 'label' => 'Escalation value', 'type' => 'number'],
                ['name' => 'escalation_every_years', 'label' => 'Escalate every (years)', 'type' => 'number'],
            ],
        ], FeeKind::cases());
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        app(SaveFees::class)->handle($record, $validated['fees'], $actor);
    }

    /** Undoing needs at least one old fee (a first save can't be undone). */
    public function canRollBack(array $before): bool
    {
        return collect($before['fees'])->contains(fn (array $fee) => $fee['enabled']);
    }
}
