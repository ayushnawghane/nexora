<?php

namespace App\Http\Controllers\Deals;

use App\Actions\Isin\ManageSchedule;
use App\Actions\Isin\SaveIsin;
use App\Actions\Isin\SendPaymentReminders;
use App\Enums\IsinPaymentKind;
use App\Enums\IsinPaymentStatus;
use App\Enums\PaymentFrequency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deals\Isin\AllotmentRequest;
use App\Http\Requests\Deals\Isin\IsinRequest;
use App\Http\Requests\Deals\Isin\MoveDueDateRequest;
use App\Http\Requests\Deals\Isin\RecordPaymentRequest;
use App\Http\Requests\Deals\Isin\ScheduleRequest;
use App\Models\DealIsin;
use App\Models\IsinPayment;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/** A deal's ISINs: their details, allotments, interest and principal schedule, and reminders. */
class DealIsinController extends Controller
{
    public function store(IsinRequest $request, Transaction $transaction, SaveIsin $save): RedirectResponse
    {
        $isin = $save->handle($transaction, null, $request->validated(), $request->user());

        return back()->with('success', "{$isin->isin} added.");
    }

    public function update(IsinRequest $request, Transaction $transaction, DealIsin $isin, SaveIsin $save): RedirectResponse
    {
        abort_unless($isin->transaction_id === $transaction->id, 404);
        $save->handle($transaction, $isin, $request->validated(), $request->user());

        return back()->with('success', "{$isin->isin} saved.");
    }

    public function allot(AllotmentRequest $request, Transaction $transaction, DealIsin $isin, SaveIsin $save): RedirectResponse
    {
        abort_unless($isin->transaction_id === $transaction->id, 404);
        $save->allot($isin, Arr::except($request->validated(), ['file']), $request->file('file'), $request->user());

        return back()->with('success', 'Allotment recorded.');
    }

    public function schedule(ScheduleRequest $request, Transaction $transaction, DealIsin $isin, ManageSchedule $schedule): RedirectResponse
    {
        abort_unless($isin->transaction_id === $transaction->id, 404);
        $user = $request->user();

        $added = match ($request->validated('source')) {
            'generate' => $schedule->generate($isin, IsinPaymentKind::from($request->validated('kind')), CarbonImmutable::parse($request->validated('first_due_on')), PaymentFrequency::from($request->validated('frequency')), $user),
            'file' => $schedule->upload($isin, $request->file('file'), $user),
            default => $schedule->addOne($isin, IsinPaymentKind::from($request->validated('kind')), CarbonImmutable::parse($request->validated('due_on')), $user),
        };

        return back()->with('success', $added === 0 ? 'Those dates are already in the schedule.' : ($added === 1 ? '1 due date added.' : "{$added} due dates added."));
    }

    public function move(MoveDueDateRequest $request, Transaction $transaction, IsinPayment $payment, ManageSchedule $schedule): RedirectResponse
    {
        abort_unless($payment->isin->transaction_id === $transaction->id, 404);
        $schedule->move($payment, CarbonImmutable::parse($request->validated('due_on')), $request->validated('reason'));

        return back()->with('success', 'Due date moved.');
    }

    public function destroy(Request $request, Transaction $transaction, IsinPayment $payment, ManageSchedule $schedule): RedirectResponse
    {
        $this->authorize('manageIsin', $transaction);
        abort_unless($payment->isin->transaction_id === $transaction->id, 404);
        $schedule->remove($payment);

        return back()->with('success', 'Due date removed.');
    }

    public function record(RecordPaymentRequest $request, Transaction $transaction, IsinPayment $payment, ManageSchedule $schedule): RedirectResponse
    {
        abort_unless($payment->isin->transaction_id === $transaction->id, 404);
        $status = IsinPaymentStatus::from($request->validated('status'));
        $schedule->record($payment, $status, Arr::except($request->validated(), ['status', 'files']), array_values($request->file('files', [])), $request->user());

        return back()->with('success', "{$payment->kind->label()} of {$payment->due_on->format('d M Y')} marked {$status->label()}.");
    }

    public function remind(Request $request, Transaction $transaction, SendPaymentReminders $reminders): RedirectResponse
    {
        $this->authorize('manageIsin', $transaction);
        $count = $reminders->now($transaction, $request->user());

        return back()->with('success', $count === 1 ? 'Reminder sent about 1 payment.' : "Reminder sent about {$count} payments.");
    }
}
