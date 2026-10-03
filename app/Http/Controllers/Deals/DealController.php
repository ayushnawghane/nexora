<?php

namespace App\Http\Controllers\Deals;

use App\Enums\DealStatus;
use App\Enums\JobSheetStatus;
use App\Enums\StatusApprovalTeam;
use App\Http\Controllers\Controller;
use App\Models\CompanyAddress;
use App\Models\CompanyContact;
use App\Models\CompanyGstin;
use App\Models\DealBilling;
use App\Models\DealJobSheetEntry;
use App\Models\DealStatusChange;
use App\Models\DealStatusRequest;
use App\Models\DealStatusVote;
use App\Models\EngagementLetter;
use App\Models\JobSheetActivity;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Tax\GstCalculator;
use App\Services\Tax\TaxNotConfigured;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/** The deal list and the deal workspace (one page, one tab per area). */
class DealController extends Controller
{
    /** Tabs a link can open; the last ones are placeholders for the Phase 2 modules. */
    private const TABS = [
        'overview', 'billing', 'status', 'job-sheet', 'activity',
        'documentation', 'execution', 'security', 'isin', 'covenants', 'credit-rating', 'outward', 'invoices',
    ];

    public function index(Request $request): Response
    {
        $this->authorize('deals.view');

        $deals = QueryBuilder::for(
            Transaction::query()
                ->whereNotNull('deal_status')
                ->with(['company:id,name', 'issueDetail:id,transaction_id,total_issue_size', 'relationshipManager:id,name'])
        )
            ->allowedFilters(
                AllowedFilter::callback('search', function (Builder $query, mixed $value) {
                    $term = '%'.trim((string) $value).'%';
                    $query->where(fn (Builder $q) => $q
                        ->where('el_number', 'like', $term)
                        ->orWhere('deal_code', 'like', $term)
                        ->orWhereHas('company', fn (Builder $c) => $c->where('name', 'like', $term)->orWhere('cin', 'like', $term)));
                }),
                AllowedFilter::callback('status', function (Builder $query, mixed $value) {
                    $status = DealStatus::tryFrom((string) $value);
                    if ($status) {
                        $query->where('deal_status', $status);
                    }
                }),
            )
            ->allowedSorts('el_date', 'deal_status_since', 'updated_at')
            ->defaultSort('-el_date')
            ->paginate($request->integer('per_page', 25) > 0 ? min($request->integer('per_page', 25), 100) : 25)
            ->withQueryString()
            ->through(fn (Transaction $t) => [
                'id' => $t->ulid,
                'company' => $t->company->name,
                'el_number' => $t->el_number,
                'deal_code' => $t->deal_code,
                'el_date' => $t->el_date?->toDateString(),
                'deal_status' => $t->deal_status?->value,
                'deal_status_label' => $t->deal_status?->label(),
                'deal_status_tone' => $t->deal_status?->tone(),
                'deal_status_since' => $t->deal_status_since?->toDateString(),
                'issue_size' => $t->issueDetail?->total_issue_size,
                'relationship_manager' => $t->relationshipManager?->name,
            ]);

        return Inertia::render('Deals/Index', [
            'deals' => $deals,
            'statuses' => DealStatus::options(),
            'filters' => [
                'search' => $request->input('filter.search', ''),
                'status' => $request->input('filter.status', ''),
            ],
            'sort' => $request->input('sort', '-el_date'),
        ]);
    }

    public function show(Request $request, Transaction $transaction, GstCalculator $gst): Response
    {
        $this->authorize('viewDeal', $transaction);
        $user = $request->user();

        $transaction->load([
            'company:id,ulid,name,cin,pan',
            'issueDetail',
            'verticalTeam.vertical:id,name',
            'relationshipManager:id,name',
            'billing' => fn ($q) => $q->with(['address.state:id,name', 'gstin:id,gstin', 'placeOfSupply:id,name', 'updater:id,name']),
            'billingContacts:id,name,salutation,email,mobile',
        ]);

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'overview';

        return Inertia::render('Deals/Show', [
            'tab' => $tab,
            'deal' => $this->summary($transaction),
            'letters' => $transaction->engagementLetters()->with('generator:id,name')->get()->map(fn (EngagementLetter $l) => [
                'version' => $l->version,
                'el_number' => $l->el_number,
                'el_date' => $l->el_date->toDateString(),
                'reason' => $l->reason,
                'generated_by' => $l->generator->name,
                'generated_at' => $l->created_at?->toIso8601String(),
                'has_pdf' => $l->hasPdf(),
            ]),
            'billing' => $this->billing($transaction, $gst),
            'status' => $this->status($transaction, $user),
            'jobSheet' => $this->jobSheet($transaction, $user),
            'activity' => $this->activity($transaction),
            'can' => [
                'editBilling' => $user->can('editDeal', $transaction),
                'requestStatus' => $user->can('requestStatus', $transaction),
                'makeJobSheet' => $user->can('makeJobSheet', $transaction),
                'checkJobSheet' => $user->can('checkJobSheet', $transaction),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Transaction $deal): array
    {
        $issue = $deal->issueDetail;
        $team = $deal->verticalTeam;

        return [
            'id' => $deal->ulid,
            'company' => ['id' => $deal->company->ulid, 'name' => $deal->company->name, 'cin' => $deal->company->cin, 'pan' => $deal->company->pan],
            'el_number' => $deal->el_number,
            'el_date' => $deal->el_date?->toDateString(),
            'deal_code' => $deal->deal_code,
            'deal_status' => $deal->deal_status?->value,
            'deal_status_label' => $deal->deal_status?->label(),
            'deal_status_tone' => $deal->deal_status?->tone(),
            'deal_status_since' => $deal->deal_status_since?->toDateString(),
            'is_open' => $deal->isOpenDeal(),
            'listing' => $issue?->listing->label(),
            'issue_type' => $issue?->issue_type->label(),
            'total_issue_size' => $issue?->total_issue_size,
            'tenure' => $issue ? trim(($issue->tenure_months ? "{$issue->tenure_months} months " : '').($issue->tenure_days ? "{$issue->tenure_days} days" : '')) : null,
            'is_secured' => $issue?->is_secured,
            'is_rated' => $issue?->is_rated,
            'vertical_team' => $team ? trim(($team->vertical?->name ? "{$team->vertical->name} · " : '').$team->name) : null,
            'relationship_manager' => $deal->relationshipManager?->name,
            'approved_at' => $deal->approved_at?->toIso8601String(),
            'closed_at' => $deal->closed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function billing(Transaction $deal, GstCalculator $gst): array
    {
        $current = $deal->billing;

        try {
            $homeStateId = $gst->homeStateId();
        } catch (TaxNotConfigured) {
            $homeStateId = null;
        }

        $taxMode = fn (?int $stateId) => $stateId === null || $homeStateId === null ? null : ($stateId === $homeStateId ? 'intra' : 'inter');

        return [
            'current' => $current ? [
                'company_address_id' => $current->company_address_id,
                'company_gstin_id' => $current->company_gstin_id,
                'address' => $this->addressLine($current->address),
                'gstin' => $current->gstin?->gstin,
                'place_of_supply' => $current->placeOfSupply->name,
                'tax_mode' => $taxMode($current->place_of_supply_state_id),
                'contact_ids' => $deal->billingContacts->modelKeys(),
                'contacts' => $deal->billingContacts->map(fn (CompanyContact $c) => ['id' => $c->id, 'name' => trim("{$c->salutation} {$c->name}"), 'email' => $c->email]),
                'updated_by' => $current->updater->name,
                'updated_at' => $current->updated_at?->toIso8601String(),
            ] : null,
            'home_state_configured' => $homeStateId !== null,
            'options' => [
                'addresses' => CompanyAddress::query()->active()->where('company_id', $deal->company_id)
                    ->with(['state:id,name', 'gstin:id,gstin'])->orderBy('type')->get()
                    ->map(fn (CompanyAddress $a) => [
                        'value' => $a->id,
                        'label' => $this->addressLine($a),
                        'description' => $a->type->label().($a->gstin ? " · GSTIN {$a->gstin->gstin}" : ''),
                        'state_id' => $a->state_id,
                        'company_gstin_id' => $a->company_gstin_id,
                        'tax_mode' => $taxMode($a->state_id),
                    ]),
                'gstins' => CompanyGstin::query()->active()->where('company_id', $deal->company_id)->with('state:id,name')->orderBy('gstin')->get()
                    ->map(fn (CompanyGstin $g) => [
                        'value' => $g->id,
                        'label' => $g->gstin,
                        'description' => $g->state->name,
                        'state_id' => $g->state_id,
                    ]),
                'contacts' => CompanyContact::query()->active()->where('company_id', $deal->company_id)->orderBy('name')->get()
                    ->map(fn (CompanyContact $c) => [
                        'id' => $c->id,
                        'name' => trim("{$c->salutation} {$c->name}"),
                        'designation' => $c->designation,
                        'email' => $c->email,
                    ]),
            ],
        ];
    }

    private function addressLine(CompanyAddress $address): string
    {
        return collect([$address->billing_name, $address->line1, $address->line2, $address->city, $address->state?->name, $address->pincode])
            ->filter()->implode(', ');
    }

    /**
     * @return array<string, mixed>
     */
    private function status(Transaction $deal, User $user): array
    {
        $requests = $deal->statusRequests()->latest('id')
            ->with(['requester:id,name', 'votes' => fn ($q) => $q->oldest('id')->with('user:id,name')])
            ->get();
        $open = $requests->first(fn (DealStatusRequest $r) => $r->isOpen());
        $from = $deal->deal_status;

        // A deal on hold may only resume at the status it was put on hold from.
        $heldFrom = $from === DealStatus::Hold
            ? $deal->statusChanges()->where('to_status', DealStatus::Hold)->latest('id')->first()?->from_status
            : null;
        $next = $from && ! $from->isFinal()
            ? array_values(array_filter($from->allowedNext(), fn (DealStatus $s) => $heldFrom === null || $s === $heldFrom || $s === DealStatus::Cancelled))
            : [];

        return [
            'next' => array_map(fn (DealStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'teams' => array_map(fn (StatusApprovalTeam $t) => $t->label(), $from->approvalTeamsFor($s)),
            ], $next),
            'needs_noc' => (bool) $from?->needsNocToLeave(),
            'min_date' => $deal->deal_status_since?->toDateString(),
            'max_date' => today()->toDateString(),
            'open_request_id' => $open?->ulid,
            'can_vote_as' => $open && $open->requested_by !== $user->id && $open->votes->doesntContain('user_id', $user->id)
                ? array_values(array_map(fn (StatusApprovalTeam $t) => ['value' => $t->value, 'label' => $t->label()],
                    array_filter($open->pendingTeams(), fn (StatusApprovalTeam $t) => $user->can($t->permission()))))
                : [],
            'can_withdraw' => $open?->requested_by === $user->id,
            'requests' => $requests->map(fn (DealStatusRequest $r) => [
                'id' => $r->ulid,
                'from' => $r->from_status->label(),
                'to' => $r->to_status->label(),
                'effective_on' => $r->effective_on->toDateString(),
                'reason' => $r->reason,
                'has_noc' => $r->noc_path !== null,
                'noc_name' => $r->noc_name,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'status_tone' => $r->status->tone(),
                'teams' => array_map(fn (StatusApprovalTeam $t) => $t->label(), $r->teams()),
                'pending_teams' => $r->isOpen() ? array_map(fn (StatusApprovalTeam $t) => $t->label(), $r->pendingTeams()) : [],
                'requested_by' => $r->requester->name,
                'requested_at' => $r->created_at?->toIso8601String(),
                'closed_at' => $r->closed_at?->toIso8601String(),
                'votes' => $r->votes->map(fn (DealStatusVote $v) => [
                    'user' => $v->user->name,
                    'team' => $v->team->label(),
                    'decision' => $v->decision->value,
                    'decision_label' => $v->decision->label(),
                    'comment' => $v->comment,
                    'at' => $v->created_at?->toIso8601String(),
                ]),
            ]),
            'history' => $deal->statusChanges()->latest('id')->with('changer:id,name')->get()->map(fn (DealStatusChange $c) => [
                'from' => $c->from_status?->label(),
                'to' => $c->to_status->label(),
                'tone' => $c->to_status->tone(),
                'effective_on' => $c->effective_on->toDateString(),
                'changed_by' => $c->changer->name,
                'at' => $c->created_at?->toIso8601String(),
            ]),
        ];
    }

    /**
     * Every active activity that applies to this deal, with the deal's entry for it if one exists.
     *
     * @return list<array<string, mixed>>
     */
    private function jobSheet(Transaction $deal, User $user): array
    {
        $entries = $deal->jobSheetEntries()->with(['maker:id,name', 'checker:id,name'])->get()->keyBy('job_sheet_activity_id');

        return JobSheetActivity::query()->appliesTo($deal->issueDetail?->listing)->orderBy('name')->get()
            ->map(function (JobSheetActivity $activity) use ($entries, $user) {
                /** @var DealJobSheetEntry|null $entry */
                $entry = $entries->get($activity->id);

                return [
                    'activity_id' => $activity->id,
                    'activity' => $activity->name,
                    'entry' => $entry ? [
                        'id' => $entry->id,
                        'status' => $entry->status->value,
                        'status_label' => $entry->status->label(),
                        'status_tone' => $entry->status->tone(),
                        'received_on' => $entry->received_on->toDateString(),
                        'maker' => $entry->maker->name,
                        'maker_comment' => $entry->maker_comment,
                        'made_at' => $entry->made_at->toIso8601String(),
                        'checker' => $entry->checker?->name,
                        'checker_comment' => $entry->checker_comment,
                        'checked_at' => $entry->checked_at?->toIso8601String(),
                        'is_mine' => $entry->maker_id === $user->id,
                    ] : null,
                    'can_submit' => $entry === null || $entry->status === JobSheetStatus::Returned,
                    'can_check' => $entry?->status === JobSheetStatus::Submitted && $entry->maker_id !== $user->id,
                ];
            })->values()->all();
    }

    /**
     * Activity log entries for the deal, its billing and its job sheet, plus status changes, newest first.
     *
     * @return list<array<string, mixed>>
     */
    private function activity(Transaction $deal): array
    {
        $subjects = [
            Transaction::class => [$deal->id],
            DealBilling::class => array_filter([$deal->billing?->id]),
            DealJobSheetEntry::class => $deal->jobSheetEntries()->pluck('id')->all(),
        ];

        $logs = Activity::query()
            ->where(function (Builder $q) use ($subjects) {
                foreach ($subjects as $type => $ids) {
                    if ($ids !== []) {
                        $q->orWhere(fn (Builder $s) => $s->where('subject_type', $type)->whereIn('subject_id', $ids));
                    }
                }
            })
            ->with('causer')
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (Activity $a) => [
                'id' => "log-{$a->id}",
                'area' => match ($a->subject_type) {
                    DealBilling::class => 'Billing',
                    DealJobSheetEntry::class => 'Job sheet',
                    default => 'Transaction',
                },
                'event' => $a->event ?? $a->description,
                'fields' => array_keys((array) ($a->properties['attributes'] ?? [])),
                'by' => $a->causer instanceof User ? $a->causer->name : null,
                'at' => $a->created_at?->toIso8601String(),
            ]);

        $changes = $deal->statusChanges()->with('changer:id,name')->get()->map(fn (DealStatusChange $c) => [
            'id' => "status-{$c->id}",
            'area' => 'Status',
            'event' => $c->from_status ? "{$c->from_status->label()} → {$c->to_status->label()}" : "Deal opened as {$c->to_status->label()}",
            'fields' => [],
            'by' => $c->changer->name,
            'at' => $c->created_at?->toIso8601String(),
        ]);

        return $logs->concat($changes)->sortByDesc('at')->values()->all();
    }
}
