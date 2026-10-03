<?php

namespace App\GodMode\Editors;

use App\Actions\Deals\ApplyDealStatus;
use App\Enums\DealStatus;
use App\Enums\StatusRequestState;
use App\GodMode\TransactionEditor;
use App\Models\DealStatusRequest;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Forces a deal to another status without the Management / Accounts vote. The state machine still
 * applies: only moves DealStatus allows, a final status closes the transaction, and the change is
 * recorded in the deal's status history. An open request is withdrawn, as it no longer applies.
 * Not undoable by rollback: moving back may not be an allowed transition; force another move instead.
 */
class DealStatusEditor extends TransactionEditor
{
    public function key(): string
    {
        return 'deal-status';
    }

    public function label(): string
    {
        return 'Deal status (forced)';
    }

    /**
     * @param  Transaction  $record
     */
    public function values(Model $record): array
    {
        return [
            'deal_status' => $record->deal_status?->value,
            'effective_on' => $record->deal_status_since?->toDateString(),
        ];
    }

    /**
     * @param  Transaction  $record
     */
    public function fields(Model $record): array
    {
        $next = $record->deal_status?->allowedNext() ?? [];

        return [
            ['name' => 'deal_status', 'label' => 'New status', 'type' => 'select', 'required' => true,
                'hint' => 'Only the moves the deal\'s current status allows. Approvals are skipped.',
                'options' => array_map(fn (DealStatus $s) => ['value' => $s->value, 'label' => $s->label()], $next)],
            ['name' => 'effective_on', 'label' => 'Effective date', 'type' => 'date', 'required' => true],
        ];
    }

    /**
     * @param  Transaction  $record
     */
    public function validate(Model $record, array $input, User $actor): array
    {
        $from = $record->deal_status;
        if ($from === null || $from->isFinal()) {
            throw ValidationException::withMessages(['deal_status' => 'This deal\'s status can\'t change any more.']);
        }

        return Validator::make($input, [
            'deal_status' => ['required', Rule::in(array_map(fn (DealStatus $s) => $s->value, $from->allowedNext()))],
            'effective_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.$record->deal_status_since?->toDateString()],
        ], [
            'deal_status.in' => "A {$from->label()} deal can't move to that status.",
            'effective_on.after_or_equal' => 'The effective date can\'t be before the deal\'s current status began.',
        ])->validate();
    }

    /**
     * @param  Transaction  $record
     */
    public function apply(Model $record, array $validated, User $actor): void
    {
        /** @var Transaction $locked */
        $locked = Transaction::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
        $locked->statusRequests()->where('status', StatusRequestState::Open)
            ->update(['status' => StatusRequestState::Withdrawn, 'closed_at' => now()]);

        /** @var DealStatusRequest $request */
        $request = $locked->statusRequests()->create([
            'from_status' => $locked->deal_status,
            'to_status' => DealStatus::from($validated['deal_status']),
            'effective_on' => CarbonImmutable::parse($validated['effective_on']),
            'reason' => 'God Mode: forced status change',
            'needs_management' => false,
            'needs_accounts' => false,
            'status' => StatusRequestState::Approved,
            'requested_by' => $actor->id,
            'closed_at' => now(),
        ]);

        app(ApplyDealStatus::class)->handle($locked, $request, $actor->id);
        $record->refresh();
    }

    public function showsSummary(): bool
    {
        return false;
    }

    public function canRollBack(array $before): bool
    {
        return false;
    }
}
