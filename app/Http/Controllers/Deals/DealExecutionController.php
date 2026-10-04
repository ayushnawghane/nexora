<?php

namespace App\Http\Controllers\Deals;

use App\Actions\Execution\CheckExecution;
use App\Actions\Execution\MarkPickedUp;
use App\Actions\Execution\RecordExecution;
use App\Actions\Execution\ScheduleExecutions;
use App\Actions\Execution\SendToExecution;
use App\Actions\Execution\WithdrawFromExecution;
use App\Enums\ExecutionStatus;
use App\Enums\SignatoryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deals\Execution\CheckExecutionRequest;
use App\Http\Requests\Deals\Execution\RecordExecutionRequest;
use App\Http\Requests\Deals\Execution\ScheduleExecutionRequest;
use App\Http\Requests\Deals\Execution\SendToExecutionRequest;
use App\Models\DealExecution;
use App\Models\PoaHolder;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Executing a deal's documents: send, schedule, record the executed copy, verify, pick up. */
class DealExecutionController extends Controller
{
    public function store(SendToExecutionRequest $request, Transaction $transaction, SendToExecution $send): RedirectResponse
    {
        $sent = $send->handle($transaction, array_map('intval', $request->validated('document_ids')), $request->user());

        return back()->with('success', $sent->count() === 1 ? 'Document sent for execution.' : "{$sent->count()} documents sent for execution.");
    }

    public function schedule(ScheduleExecutionRequest $request, Transaction $transaction, ScheduleExecutions $schedule): RedirectResponse
    {
        $type = SignatoryType::from($request->validated('signatory_type'));
        $scheduled = $schedule->handle(
            $transaction,
            array_map('intval', $request->validated('execution_ids')),
            $request->validated('place'),
            CarbonImmutable::createFromFormat('Y-m-d\TH:i', $request->validated('scheduled_at')) ?: throw new \InvalidArgumentException('Bad date.'),
            $type,
            $type === SignatoryType::Internal ? User::query()->find($request->validated('signatory_user_id')) : null,
            $type === SignatoryType::External ? PoaHolder::query()->find($request->validated('poa_holder_id')) : null,
            $request->user(),
        );

        return back()->with('success', ($scheduled->count() === 1 ? 'Execution scheduled.' : "{$scheduled->count()} executions scheduled.").' The signatory has been emailed.');
    }

    public function record(RecordExecutionRequest $request, Transaction $transaction, DealExecution $execution, RecordExecution $record): RedirectResponse
    {
        abort_unless($execution->transaction_id === $transaction->id, 404);

        $documentDate = $request->validated('document_date');
        $record->handle(
            $execution,
            $request->file('file'),
            $documentDate ? CarbonImmutable::parse($documentDate) : null,
            CarbonImmutable::parse($request->validated('executed_on')),
            $request->validated('comments'),
            $request->user(),
        );

        return back()->with('success', 'Executed copy uploaded and sent for checking.');
    }

    public function check(CheckExecutionRequest $request, Transaction $transaction, DealExecution $execution, CheckExecution $check): RedirectResponse
    {
        abort_unless($execution->transaction_id === $transaction->id, 404);

        $result = $check->handle($execution, $request->user(), ExecutionStatus::from($request->validated('decision')), $request->validated('comment'));

        return back()->with('success', $result->status === ExecutionStatus::Verified ? 'Execution verified.' : 'Execution sent back.');
    }

    public function destroy(Request $request, Transaction $transaction, DealExecution $execution, WithdrawFromExecution $withdraw): RedirectResponse
    {
        $this->authorize('manageExecution', $transaction);
        abort_unless($execution->transaction_id === $transaction->id, 404);

        $withdraw->handle($execution);

        return back()->with('success', 'Document taken out of execution.');
    }

    public function pickUp(Request $request, Transaction $transaction, MarkPickedUp $pickUp): RedirectResponse
    {
        $this->authorize('custody', $transaction);

        $count = $pickUp->handle($transaction, $request->user());

        return back()->with('success', $count === 1 ? 'Executed document marked as picked up.' : "{$count} executed documents marked as picked up.");
    }
}
