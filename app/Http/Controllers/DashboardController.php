<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\ConditionStatus;
use App\Enums\DealStatus;
use App\Enums\ExecutionStatus;
use App\Enums\InvoiceStatus;
use App\Enums\IsinPaymentStatus;
use App\Enums\JobSheetStatus;
use App\Enums\StatusApprovalTeam;
use App\Enums\StatusRequestState;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Billing\BillingQueueController;
use App\Models\ApprovalRequest;
use App\Models\DealCondition;
use App\Models\DealDiligenceItem;
use App\Models\DealExecution;
use App\Models\DealJobSheetEntry;
use App\Models\DealStatusRequest;
use App\Models\FeeSchedulePeriod;
use App\Models\Invoice;
use App\Models\IsinPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Support\FinancialYear;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Headline numbers and the signed-in user's work queue (votes, status changes, job sheet, CP/CS and
 * execution checks, documents to sign, and the custody pickup list). Each section only appears for people with
 * the permission it needs.
 */
class DashboardController extends Controller
{
    private const QUEUE_LIMIT = 10;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'kpis' => $user->can('deals.view') || $user->can('transactions.view') || $user->can('billing.view') ? $this->kpis($user) : null,
            'queue' => $this->queue($user),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function kpis(User $user): array
    {
        $kpis = [];
        $fyStart = CarbonImmutable::create(FinancialYear::startYear(today()), 4, 1);

        if ($user->can('deals.view')) {
            $open = Transaction::query()->whereNotNull('deal_status')->whereNotIn('deal_status', $this->finalStatuses());
            $issueTotal = (clone $open)->with('issueDetail:id,transaction_id,total_issue_size')->get()
                ->reduce(fn (BigDecimal $sum, Transaction $t) => $sum->plus($t->issueDetail->total_issue_size ?? '0'), BigDecimal::zero());

            $kpis[] = ['key' => 'open', 'label' => 'Open deals', 'value' => (clone $open)->count(), 'href' => route('deals.index')];
            $kpis[] = ['key' => 'live', 'label' => 'Live deals', 'value' => Transaction::query()->where('deal_status', DealStatus::Live)->count(), 'href' => route('deals.index', ['filter' => ['status' => 'live']])];
            $kpis[] = ['key' => 'fy', 'label' => 'Deals opened this FY ('.FinancialYear::short(today()).')', 'value' => Transaction::query()->whereNotNull('deal_status')->where('el_date', '>=', $fyStart->toDateString())->count(), 'href' => route('deals.index')];
            $overdue = IsinPayment::query()->where('status', IsinPaymentStatus::Due)->whereDate('due_on', '<', today())
                ->whereHas('isin.transaction', fn (Builder $q) => $q->whereNotIn('deal_status', $this->finalStatuses()))->count();
            $kpis[] = ['key' => 'overdue_payments', 'label' => 'Debenture payments overdue', 'value' => $overdue, 'href' => route('isins.index', ['filter' => ['due' => 'overdue']])];
            $kpis[] = ['key' => 'issue', 'label' => 'Issue size under trusteeship', 'value' => (string) $issueTotal->toScale(2), 'money' => true, 'href' => route('deals.index')];
        }

        if ($user->can('billing.view')) {
            $kpis[] = ['key' => 'receivable', 'label' => 'Outstanding on invoices', 'value' => (string) BigDecimal::of((string) (Invoice::query()->due()->sum('balance_due') ?: '0'))->toScale(2), 'money' => true, 'href' => route('invoices.index', ['filter' => ['tab' => 'due']])];
            $kpis[] = ['key' => 'overdue_invoices', 'label' => 'Invoices overdue', 'value' => Invoice::query()->overdue()->count(), 'href' => route('invoices.index', ['filter' => ['tab' => 'due']])];
        }

        if ($user->can('transactions.view')) {
            $kpis[] = ['key' => 'drafts', 'label' => 'Drafts', 'value' => Transaction::query()->inStatus(TransactionStatus::Draft, TransactionStatus::Rejected)->count(), 'href' => route('transactions.drafts')];
            $kpis[] = ['key' => 'pending', 'label' => 'Pending approval', 'value' => Transaction::query()->inStatus(TransactionStatus::PendingApproval)->count(), 'href' => route('transactions.pending')];
        }

        return $kpis;
    }

    /**
     * Things waiting on this user, oldest first.
     *
     * @return list<array<string, mixed>>
     */
    private function queue(User $user): array
    {
        $items = collect();

        if ($user->can('approvals.vote')) {
            ApprovalRequest::query()
                ->where('status', ApprovalStatus::Open)
                ->where('requested_by', '!=', $user->id)
                ->whereDoesntHave('votes', fn (Builder $q) => $q->where('user_id', $user->id))
                ->with('transaction.company:id,name')
                ->oldest()->limit(self::QUEUE_LIMIT)->get()
                ->each(fn (ApprovalRequest $r) => $items->push([
                    'id' => "approval-{$r->id}",
                    'kind' => 'Transaction approval',
                    'title' => $r->transaction->company->name,
                    'detail' => 'Waiting for your vote',
                    'href' => route('transactions.show', $r->transaction->ulid),
                    'at' => $r->created_at?->toIso8601String(),
                ]));
        }

        $teams = array_values(array_filter(StatusApprovalTeam::cases(), fn (StatusApprovalTeam $t) => $user->can($t->permission())));
        if ($teams !== []) {
            DealStatusRequest::query()
                ->where('status', StatusRequestState::Open)
                ->where('requested_by', '!=', $user->id)
                ->whereDoesntHave('votes', fn (Builder $q) => $q->where('user_id', $user->id))
                ->with(['transaction.company:id,name', 'votes'])
                ->oldest()->get()
                ->filter(fn (DealStatusRequest $r) => array_intersect(
                    array_map(fn ($t) => $t->value, $r->pendingTeams()),
                    array_map(fn ($t) => $t->value, $teams),
                ) !== [])
                ->take(self::QUEUE_LIMIT)
                ->each(fn (DealStatusRequest $r) => $items->push([
                    'id' => "status-{$r->id}",
                    'kind' => 'Status change',
                    'title' => $r->transaction->company->name,
                    'detail' => "{$r->from_status->label()} → {$r->to_status->label()}",
                    'href' => route('deals.show', ['transaction' => $r->transaction->ulid, 'tab' => 'status']),
                    'at' => $r->created_at?->toIso8601String(),
                ]));
        }

        if ($user->can('deals.jobsheet.check')) {
            DealJobSheetEntry::query()
                ->where('status', JobSheetStatus::Submitted)
                ->where('maker_id', '!=', $user->id)
                ->whereHas('transaction', fn (Builder $q) => $q->whereNotIn('deal_status', $this->finalStatuses()))
                ->with(['transaction.company:id,name', 'activity:id,name'])
                ->oldest('made_at')->limit(self::QUEUE_LIMIT)->get()
                ->each(fn (DealJobSheetEntry $e) => $items->push([
                    'id' => "check-{$e->id}",
                    'kind' => 'Job sheet check',
                    'title' => $e->transaction->company->name,
                    'detail' => $e->activity->name,
                    'href' => route('deals.show', ['transaction' => $e->transaction->ulid, 'tab' => 'job-sheet']),
                    'at' => $e->made_at->toIso8601String(),
                ]));
        }

        if ($user->can('deals.jobsheet.make')) {
            DealJobSheetEntry::query()
                ->where('status', JobSheetStatus::Returned)
                ->where('maker_id', $user->id)
                ->with(['transaction.company:id,name', 'activity:id,name'])
                ->oldest('checked_at')->limit(self::QUEUE_LIMIT)->get()
                ->each(fn (DealJobSheetEntry $e) => $items->push([
                    'id' => "returned-{$e->id}",
                    'kind' => 'Sent back to you',
                    'title' => $e->transaction->company->name,
                    'detail' => $e->activity->name.($e->checker_comment ? " · “{$e->checker_comment}”" : ''),
                    'href' => route('deals.show', ['transaction' => $e->transaction->ulid, 'tab' => 'job-sheet']),
                    'at' => $e->checked_at?->toIso8601String(),
                ]));
        }

        if ($user->can('deals.documents.verify')) {
            DealCondition::query()
                ->where('status', ConditionStatus::Submitted)
                ->where('submitted_by', '!=', $user->id)
                ->whereHas('transaction', fn (Builder $q) => $q->whereNotIn('deal_status', $this->finalStatuses()))
                ->with('transaction.company:id,name')
                ->oldest('submitted_at')->limit(self::QUEUE_LIMIT)->get()
                ->each(fn (DealCondition $c) => $items->push([
                    'id' => "condition-{$c->id}",
                    'kind' => "{$c->stage->short()} check",
                    'title' => $c->transaction->company->name,
                    'detail' => $c->name,
                    'href' => route('deals.show', ['transaction' => $c->transaction->ulid, 'tab' => 'documentation']),
                    'at' => $c->submitted_at?->toIso8601String(),
                ]));
        }

        if ($user->can('deals.documents.manage')) {
            DealCondition::query()
                ->where('status', ConditionStatus::Returned)
                ->where('submitted_by', $user->id)
                ->with('transaction.company:id,name')
                ->oldest('checked_at')->limit(self::QUEUE_LIMIT)->get()
                ->each(fn (DealCondition $c) => $items->push([
                    'id' => "condition-returned-{$c->id}",
                    'kind' => 'Sent back to you',
                    'title' => $c->transaction->company->name,
                    'detail' => $c->name.($c->checker_comment ? " · “{$c->checker_comment}”" : ''),
                    'href' => route('deals.show', ['transaction' => $c->transaction->ulid, 'tab' => 'documentation']),
                    'at' => $c->checked_at?->toIso8601String(),
                ]));
        }

        $securityLink = fn (DealDiligenceItem $d) => route('deals.show', ['transaction' => $d->transaction->ulid, 'tab' => 'security']);

        if ($user->can('deals.security.verify')) {
            DealDiligenceItem::query()
                ->where('status', ConditionStatus::Submitted)
                ->where('submitted_by', '!=', $user->id)
                ->whereHas('transaction', fn (Builder $q) => $q->whereNotIn('deal_status', $this->finalStatuses()))
                ->with('transaction.company:id,name')
                ->oldest('submitted_at')->limit(self::QUEUE_LIMIT)->get()
                ->each(fn (DealDiligenceItem $d) => $items->push([
                    'id' => "diligence-{$d->id}",
                    'kind' => 'Due diligence check',
                    'title' => $d->transaction->company->name,
                    'detail' => $d->title,
                    'href' => $securityLink($d),
                    'at' => $d->submitted_at?->toIso8601String(),
                ]));
        }

        if ($user->can('deals.security.manage')) {
            DealDiligenceItem::query()
                ->where('status', ConditionStatus::Returned)
                ->where('submitted_by', $user->id)
                ->with('transaction.company:id,name')
                ->oldest('checked_at')->limit(self::QUEUE_LIMIT)->get()
                ->each(fn (DealDiligenceItem $d) => $items->push([
                    'id' => "diligence-returned-{$d->id}",
                    'kind' => 'Sent back to you',
                    'title' => $d->transaction->company->name,
                    'detail' => $d->title.($d->checker_comment ? " · “{$d->checker_comment}”" : ''),
                    'href' => $securityLink($d),
                    'at' => $d->checked_at?->toIso8601String(),
                ]));
        }

        if ($user->can('deals.isin.manage')) {
            IsinPayment::query()
                ->where('status', IsinPaymentStatus::Due)
                ->whereDate('due_on', '<', today())
                ->whereHas('isin.transaction', fn (Builder $q) => $q->whereNotIn('deal_status', $this->finalStatuses()))
                ->with(['isin:id,transaction_id,isin', 'isin.transaction:id,ulid,company_id', 'isin.transaction.company:id,name'])
                ->oldest('due_on')->limit(self::QUEUE_LIMIT)->get()
                ->each(fn (IsinPayment $p) => $items->push([
                    'id' => "isin-payment-{$p->id}",
                    'kind' => 'Payment overdue',
                    'title' => $p->isin->transaction->company->name,
                    'detail' => "{$p->isin->isin} · {$p->kind->label()} due {$p->due_on->format('d M Y')}",
                    'href' => route('deals.show', ['transaction' => $p->isin->transaction->ulid, 'tab' => 'isin']),
                    'at' => $p->due_on->toIso8601String(),
                ]));
        }

        $invoiceItem = fn (Invoice $i, string $kind, string $detail, ?string $at) => [
            'id' => "invoice-{$kind}-{$i->id}",
            'kind' => $kind,
            'title' => $i->transaction->company->name,
            'detail' => $detail,
            'href' => route('invoices.show', $i->ulid),
            'at' => $at,
        ];
        $withDeal = ['transaction:id,ulid,company_id', 'transaction.company:id,name'];

        if ($user->can('billing.approve')) {
            Invoice::query()->where('status', InvoiceStatus::Draft)->whereNull('returned_at')->where('created_by', '!=', $user->id)
                ->with([...$withDeal, 'creator:id,name'])->oldest('id')->limit(self::QUEUE_LIMIT)->get()
                ->each(fn (Invoice $i) => $items->push($invoiceItem($i, 'Invoice to issue', "{$i->kind->label()} · ".Money::format($i->total)." · drafted by {$i->creator->name}", $i->created_at?->toIso8601String())));
        }

        if ($user->can('billing.raise')) {
            Invoice::query()->where('status', InvoiceStatus::Draft)->whereNotNull('returned_at')->where('created_by', $user->id)
                ->with($withDeal)->oldest('returned_at')->limit(self::QUEUE_LIMIT)->get()
                ->each(fn (Invoice $i) => $items->push($invoiceItem($i, 'Sent back to you', "{$i->kind->label()} · “{$i->returned_reason}”", $i->returned_at?->toIso8601String())));

            // Deals with a fee period whose bill date has passed and nothing billing it yet.
            BillingQueueController::due()->whereDate('bill_date', '<=', today())
                ->with(['feeLine:id,transaction_id,kind', 'feeLine.transaction:id,ulid,company_id', 'feeLine.transaction.company:id,name'])
                ->orderBy('bill_date')->limit(500)->get()
                ->unique(fn (FeeSchedulePeriod $p) => $p->feeLine->transaction_id)->take(self::QUEUE_LIMIT)
                ->each(fn (FeeSchedulePeriod $p) => $items->push([
                    'id' => "to-bill-{$p->feeLine->transaction_id}",
                    'kind' => 'Ready to bill',
                    'title' => $p->feeLine->transaction->company->name,
                    'detail' => "{$p->feeLine->kind->label()} from {$p->from_date->format('d M Y')}, bill date {$p->bill_date->format('d M Y')}",
                    'href' => route('deals.show', ['transaction' => $p->feeLine->transaction->ulid, 'tab' => 'invoices']),
                    'at' => $p->bill_date->toIso8601String(),
                ]));
        }

        if ($user->can('billing.receipts')) {
            Invoice::query()->overdue()->with($withDeal)->oldest('invoice_date')->limit(self::QUEUE_LIMIT)->get()
                ->each(fn (Invoice $i) => $items->push($invoiceItem($i, 'Invoice overdue', "{$i->number} · ".Money::format($i->balance_due).' outstanding', $i->invoice_date?->toIso8601String())));
        }

        $executionLink = fn (DealExecution $e) => route('deals.show', ['transaction' => $e->transaction->ulid, 'tab' => 'execution']);

        if ($user->can('deals.execution.verify')) {
            DealExecution::query()
                ->where('status', ExecutionStatus::Executed)
                ->where('uploaded_by', '!=', $user->id)
                ->whereHas('transaction', fn (Builder $q) => $q->whereNotIn('deal_status', $this->finalStatuses()))
                ->with(['transaction.company:id,name', 'document:id,name'])
                ->oldest('uploaded_at')->limit(self::QUEUE_LIMIT)->get()
                ->each(fn (DealExecution $e) => $items->push([
                    'id' => "execution-{$e->id}",
                    'kind' => 'Execution check',
                    'title' => $e->transaction->company->name,
                    'detail' => $e->document->name,
                    'href' => $executionLink($e),
                    'at' => $e->uploaded_at?->toIso8601String(),
                ]));
        }

        if ($user->can('deals.execution.manage')) {
            DealExecution::query()
                ->where('status', ExecutionStatus::Returned)
                ->where('uploaded_by', $user->id)
                ->with(['transaction.company:id,name', 'document:id,name'])
                ->oldest('checked_at')->limit(self::QUEUE_LIMIT)->get()
                ->each(fn (DealExecution $e) => $items->push([
                    'id' => "execution-returned-{$e->id}",
                    'kind' => 'Sent back to you',
                    'title' => $e->transaction->company->name,
                    'detail' => $e->document->name.($e->checker_comment ? " · “{$e->checker_comment}”" : ''),
                    'href' => $executionLink($e),
                    'at' => $e->checked_at?->toIso8601String(),
                ]));
        }

        // Documents this user signs for Beacon.
        DealExecution::query()
            ->where('status', ExecutionStatus::Scheduled)
            ->where('signatory_user_id', $user->id)
            ->with(['transaction.company:id,name', 'document:id,name'])
            ->oldest('scheduled_at')->limit(self::QUEUE_LIMIT)->get()
            ->each(fn (DealExecution $e) => $items->push([
                'id' => "sign-{$e->id}",
                'kind' => 'To sign',
                'title' => $e->transaction->company->name,
                'detail' => $e->document->name.($e->scheduled_at ? ' · '.$e->scheduled_at->format('d M Y, H:i').", {$e->place}" : ''),
                'href' => $executionLink($e),
                'at' => $e->scheduled_at?->toIso8601String(),
            ]));

        // The pickup list: deals whose executed documents are all verified and not yet picked up.
        if ($user->can('deals.execution.custody')) {
            Transaction::query()
                ->whereNotNull('deal_status')
                ->whereHas('executions', fn (Builder $q) => $q->whereNull('picked_up_at'))
                ->whereDoesntHave('executions', fn (Builder $q) => $q->where('status', '!=', ExecutionStatus::Verified))
                ->with('company:id,name')
                ->withMax('executions', 'checked_at')
                ->orderBy('executions_max_checked_at')->limit(self::QUEUE_LIMIT)->get()
                ->each(fn (Transaction $t) => $items->push([
                    'id' => "pickup-{$t->id}",
                    'kind' => 'Ready for pickup',
                    'title' => $t->company->name,
                    'detail' => 'Executed documents verified, ready for custody',
                    'href' => route('deals.show', ['transaction' => $t->ulid, 'tab' => 'execution']),
                    'at' => $t->getAttribute('executions_max_checked_at') ? Carbon::parse($t->getAttribute('executions_max_checked_at'))->toIso8601String() : null,
                ]));
        }

        return $items->sortBy('at')->values()->all();
    }

    /**
     * @return list<string>
     */
    private function finalStatuses(): array
    {
        return array_values(array_map(
            fn (DealStatus $s) => $s->value,
            array_filter(DealStatus::cases(), fn (DealStatus $s) => $s->isFinal()),
        ));
    }
}
