<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\DealStatus;
use App\Enums\JobSheetStatus;
use App\Enums\StatusApprovalTeam;
use App\Enums\StatusRequestState;
use App\Enums\TransactionStatus;
use App\Models\ApprovalRequest;
use App\Models\DealJobSheetEntry;
use App\Models\DealStatusRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Support\FinancialYear;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Headline numbers and the signed-in user's work queue. Each section only appears for people with
 * the permission it needs.
 */
class DashboardController extends Controller
{
    private const QUEUE_LIMIT = 10;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'kpis' => $user->can('deals.view') || $user->can('transactions.view') ? $this->kpis($user) : null,
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
            $kpis[] = ['key' => 'issue', 'label' => 'Issue size under trusteeship', 'value' => (string) $issueTotal->toScale(2), 'money' => true, 'href' => route('deals.index')];
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
