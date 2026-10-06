<?php

namespace App\Http\Controllers\Deals;

use App\Enums\AllotmentKind;
use App\Enums\ConditionStatus;
use App\Enums\CouponType;
use App\Enums\DayCount;
use App\Enums\DealDocumentKind;
use App\Enums\DealStatus;
use App\Enums\DiligenceKind;
use App\Enums\ExecutionStatus;
use App\Enums\HolidayConvention;
use App\Enums\IsinPaymentKind;
use App\Enums\IsinPaymentStatus;
use App\Enums\JobSheetStatus;
use App\Enums\Listing;
use App\Enums\OwnerIdType;
use App\Enums\PaymentFrequency;
use App\Enums\Placement;
use App\Enums\RedemptionBasis;
use App\Enums\RegistrationKind;
use App\Enums\RegistrationStatus;
use App\Enums\SecurityNature;
use App\Enums\SignatoryType;
use App\Enums\StatusApprovalTeam;
use App\Http\Controllers\Billing\InvoiceController;
use App\Http\Controllers\Controller;
use App\Models\AssetType;
use App\Models\ChargeType;
use App\Models\CompanyAddress;
use App\Models\CompanyContact;
use App\Models\CompanyGstin;
use App\Models\ConditionDocument;
use App\Models\DealBilling;
use App\Models\DealCondition;
use App\Models\DealDiligenceItem;
use App\Models\DealDocument;
use App\Models\DealExecution;
use App\Models\DealExpense;
use App\Models\DealIsin;
use App\Models\DealJobSheetEntry;
use App\Models\DealSecurity;
use App\Models\DealStatusChange;
use App\Models\DealStatusRequest;
use App\Models\DealStatusVote;
use App\Models\DocumentFile;
use App\Models\EmpanelledAgency;
use App\Models\EngagementLetter;
use App\Models\FeeSchedulePeriod;
use App\Models\Invoice;
use App\Models\IsinAllotment;
use App\Models\IsinPayment;
use App\Models\IssuingAuthority;
use App\Models\JobSheetActivity;
use App\Models\LegalDocumentType;
use App\Models\PoaHolder;
use App\Models\SecurityRegistration;
use App\Models\SecurityRegistrationEvent;
use App\Models\SecurityType;
use App\Models\State;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Tax\GstCalculator;
use App\Services\Tax\TaxNotConfigured;
use Brick\Math\BigDecimal;
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
            'documentation' => $this->documentation($transaction, $user),
            'execution' => $this->execution($transaction, $user),
            'security' => $this->security($transaction, $user),
            'isin' => $this->isin($transaction),
            'invoices' => $user->can('billing.view') ? $this->invoices($transaction) : null,
            'activity' => $this->activity($transaction),
            'can' => [
                'editBilling' => $user->can('editDeal', $transaction),
                'requestStatus' => $user->can('requestStatus', $transaction),
                'makeJobSheet' => $user->can('makeJobSheet', $transaction),
                'checkJobSheet' => $user->can('checkJobSheet', $transaction),
                'manageDocuments' => $user->can('manageDocuments', $transaction),
                'verifyDocuments' => $user->can('verifyDocuments', $transaction),
                'manageExecution' => $user->can('manageExecution', $transaction),
                'verifyExecution' => $user->can('verifyExecution', $transaction),
                'custody' => $user->can('custody', $transaction),
                'manageSecurity' => $user->can('manageSecurity', $transaction),
                'satisfyRegistration' => $user->can('satisfyRegistration', $transaction),
                'verifySecurity' => $user->can('verifySecurity', $transaction),
                'manageIsin' => $user->can('manageIsin', $transaction),
                'viewBilling' => $user->can('billing.view'),
                'raiseBilling' => $user->can('billing.raise'),
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
                'final' => $s->isFinal(),
            ], $next),
            // A warning, not a block (PHASE-2-PLAN §4 #3): closing a deal with charges still registered.
            'active_registrations' => $deal->registrations()->where('status', RegistrationStatus::Active)->count(),
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
     * The deal's legal documents and CP/CS items, with what the add forms can offer.
     *
     * @return array<string, mixed>
     */
    private function documentation(Transaction $deal, User $user): array
    {
        $fileWith = ['uploader:id,name', 'remover:id,name'];
        $listing = $deal->issueDetail?->listing;
        $secured = $deal->issueDetail?->is_secured;

        $kindOrder = array_flip(array_map(fn (DealDocumentKind $k) => $k->value, DealDocumentKind::cases()));
        $documents = $deal->dealDocuments()
            ->with(['type:id,name,category', 'creator:id,name', 'execution:id,deal_document_id,status', 'files' => fn ($q) => $q->with($fileWith)])
            ->get()
            ->sortBy([
                fn (DealDocument $a, DealDocument $b) => strcasecmp($a->type->name, $b->type->name),
                fn (DealDocument $a, DealDocument $b) => $kindOrder[$a->kind->value] <=> $kindOrder[$b->kind->value],
                fn (DealDocument $a, DealDocument $b) => $a->sequence <=> $b->sequence,
            ])
            ->values()
            ->map(function (DealDocument $d) {
                $current = $d->files->first(fn (DocumentFile $f) => $f->removed_at === null);

                return [
                    'id' => $d->id,
                    'name' => $d->name,
                    'kind' => $d->kind->value,
                    'kind_label' => $d->kind->label(),
                    'category' => $d->type->category->value,
                    'category_label' => $d->type->category->label(),
                    'type_id' => $d->legal_document_type_id,
                    'added_by' => $d->creator->name,
                    'added_at' => $d->created_at?->toIso8601String(),
                    'current' => $current?->present(),
                    'history' => $d->files->reject(fn (DocumentFile $f) => $current !== null && $f->is($current))
                        ->map(fn (DocumentFile $f) => $f->present())->values(),
                    'in_execution' => $d->execution !== null,
                    'execution_label' => $d->execution?->status->label(),
                ];
            });

        $conditions = $deal->conditions()
            ->with(['issuingAuthority:id,name', 'submitter:id,name', 'checker:id,name', 'files' => fn ($q) => $q->with($fileWith)])
            ->orderBy('id')->get()
            ->map(fn (DealCondition $c) => [
                'id' => $c->id,
                'stage' => $c->stage->value,
                'name' => $c->name,
                'from_master' => $c->condition_document_id !== null,
                'issuing_authority' => $c->issuingAuthority?->name,
                'due_on' => $c->due_on?->toDateString(),
                'overdue' => $c->isOverdue(),
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'status_tone' => $c->status->tone(),
                'is_open' => $c->status->isOpen(),
                'submitted_by' => $c->submitter?->name,
                'submitted_at' => $c->submitted_at?->toIso8601String(),
                'checker' => $c->checker?->name,
                'checker_comment' => $c->checker_comment,
                'checked_at' => $c->checked_at?->toIso8601String(),
                'waived_reason' => $c->waived_reason,
                'files' => $c->files->whereNull('removed_at')->sortBy('id')->map(fn (DocumentFile $f) => $f->present())->values(),
                'removed_files' => $c->files->whereNotNull('removed_at')->map(fn (DocumentFile $f) => $f->present())->values(),
                'can_check' => $c->status === ConditionStatus::Submitted && $c->submitted_by !== $user->id,
                'is_mine' => $c->submitted_by === $user->id,
                'can_remove' => $c->status === ConditionStatus::Pending && $c->files->isEmpty(),
            ]);

        $taken = $deal->conditions()->whereNotNull('condition_document_id')->pluck('condition_document_id')->all();

        return [
            'documents' => $documents,
            'conditions' => $conditions,
            'issue' => $listing && $secured !== null ? $listing->label().', '.($secured ? 'secured' : 'unsecured') : null,
            'options' => [
                'types' => LegalDocumentType::query()->forProduct($deal->product_id)->orderBy('name')->get(['id', 'name', 'category'])
                    ->map(fn (LegalDocumentType $t) => [
                        'value' => $t->id,
                        'label' => $t->name,
                        'description' => $t->category->label(),
                        'on_deal' => $documents->contains(fn (array $d) => $d['type_id'] === $t->id && $d['kind'] === DealDocumentKind::Standard->value),
                    ]),
                'kinds' => DealDocumentKind::options(),
                'conditions' => ConditionDocument::query()->active()->whereNotIn('id', $taken)->with('issuingAuthority:id,name')->orderBy('name')->get()
                    ->map(fn (ConditionDocument $d) => [
                        'value' => $d->id,
                        'stage' => $d->stage->value,
                        'label' => $d->name,
                        'authority' => $d->issuingAuthority?->name,
                        'suggested' => $d->isSuggestedFor($listing, $secured),
                    ]),
                'authorities' => IssuingAuthority::query()->active()->orderBy('name')->get(['id', 'name'])
                    ->map(fn (IssuingAuthority $a) => ['value' => $a->id, 'label' => $a->name]),
            ],
        ];
    }

    /**
     * The deal's documents in execution, the documents ready to send, and the choices for scheduling.
     *
     * @return array<string, mixed>
     */
    private function execution(Transaction $deal, User $user): array
    {
        $fileWith = ['uploader:id,name', 'remover:id,name'];
        $executions = $deal->executions()
            ->with(['document.type:id,name', 'signatoryUser:id,name', 'poaHolder:id,name', 'uploader:id,name', 'checker:id,name', 'pickedUpBy:id,name',
                'files' => fn ($q) => $q->with($fileWith)])
            ->orderBy('id')->get();

        $rows = $executions->map(function (DealExecution $e) use ($user) {
            $current = $e->files->first(fn (DocumentFile $f) => $f->removed_at === null);

            return [
                'id' => $e->id,
                'document' => $e->document->name,
                'kind_label' => $e->document->kind->label(),
                'status' => $e->status->value,
                'status_label' => $e->status->label(),
                'status_tone' => $e->status->tone(),
                'place' => $e->place,
                'scheduled_at' => $e->scheduled_at?->format('Y-m-d\TH:i'),
                'signatory' => $e->signatoryName(),
                'signatory_type' => $e->signatory_type?->value,
                'signatory_type_label' => $e->signatory_type?->label(),
                'document_date' => $e->document_date?->toDateString(),
                'executed_on' => $e->executed_on?->toDateString(),
                'comments' => $e->comments,
                'uploaded_by' => $e->uploader?->name,
                'uploaded_at' => $e->uploaded_at?->toIso8601String(),
                'checker' => $e->checker?->name,
                'checker_comment' => $e->checker_comment,
                'checked_at' => $e->checked_at?->toIso8601String(),
                'picked_up_by' => $e->pickedUpBy?->name,
                'picked_up_at' => $e->picked_up_at?->toIso8601String(),
                'current' => $current?->present(),
                'history' => $e->files->reject(fn (DocumentFile $f) => $current !== null && $f->is($current))->map(fn (DocumentFile $f) => $f->present())->values(),
                'can_schedule' => $e->status->canSchedule(),
                'can_record' => $e->status->canRecord(),
                'can_check' => $e->status === ExecutionStatus::Executed && $e->uploaded_by !== $user->id,
                'is_mine' => $e->uploaded_by === $user->id,
                'can_withdraw' => $e->status->canSchedule() && $e->files->isEmpty(),
            ];
        });

        $allVerified = $executions->isNotEmpty() && $executions->every(fn (DealExecution $e) => $e->status === ExecutionStatus::Verified);

        return [
            'executions' => $rows,
            'ready' => $deal->dealDocuments()->whereDoesntHave('execution')->whereHas('currentFile')->orderBy('name')->get(['id', 'name'])
                ->map(fn (DealDocument $d) => ['value' => $d->id, 'label' => $d->name]),
            'pickup' => [
                'ready' => $allVerified && $executions->contains(fn (DealExecution $e) => $e->picked_up_at === null),
                'all_picked_up' => $allVerified && $executions->every(fn (DealExecution $e) => $e->picked_up_at !== null),
            ],
            // Stack moved a deal to Live by itself once its key document was verified; here the move
            // still goes through the status approval, so the tab only points to it.
            'suggest_live' => $allVerified && in_array($deal->deal_status, [DealStatus::Preliminary, DealStatus::Documentation], true),
            'options' => [
                'types' => SignatoryType::options(),
                'signatories' => User::query()->where('is_active', true)->where('is_authorised_signatory', true)->orderBy('name')->get(['id', 'name'])
                    ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name]),
                'poa_holders' => PoaHolder::query()->active()->orderBy('name')->get(['id', 'name', 'valid_from', 'valid_till'])
                    ->map(fn (PoaHolder $p) => [
                        'value' => $p->id,
                        'label' => $p->name,
                        'valid_from' => $p->valid_from?->toDateString(),
                        'valid_till' => $p->valid_till?->toDateString(),
                        'description' => $p->valid_till ? 'Valid till '.$p->valid_till->format('d M Y') : null,
                    ]),
            ],
        ];
    }

    /**
     * The deal's securities, their registrations and its due diligence, with the form choices.
     *
     * @return array<string, mixed>
     */
    private function security(Transaction $deal, User $user): array
    {
        $fileWith = ['uploader:id,name', 'remover:id,name'];
        $securities = $deal->securities()
            ->with(['document:id,name', 'assetType:id,name', 'chargeType:id,name', 'state:id,name', 'securityTypes:id,name', 'registrations:id,kind,status'])
            ->orderBy('deal_document_id')->orderBy('id')->get();
        $withDiligence = $deal->diligenceItems()->whereNotNull('deal_security_id')->pluck('deal_security_id')->unique()->all();

        $registrations = $deal->registrations()
            ->with(['securities.securityTypes:id,name', 'events' => fn ($q) => $q->with(['creator:id,name', 'files' => fn ($f) => $f->with($fileWith)])])
            ->orderBy('kind')->orderBy('id')->get();

        $diligence = $deal->diligenceItems()
            ->with(['security.securityTypes:id,name', 'agency:id,code,name', 'submitter:id,name', 'checker:id,name', 'files' => fn ($q) => $q->with($fileWith)])
            ->orderBy('kind')->orderBy('id')->get();

        return [
            'securities' => $securities->map(fn (DealSecurity $s) => [
                'id' => $s->id,
                'summary' => $s->summary(),
                'deal_document_id' => $s->deal_document_id,
                'document' => $s->document->name,
                'nature' => $s->nature->value,
                'nature_label' => $s->nature->label(),
                'asset_owner' => $s->asset_owner,
                'owner_id_type' => $s->owner_id_type?->value,
                'owner_id_number' => $s->owner_id_number,
                'asset_type_id' => $s->asset_type_id,
                'asset_type' => $s->assetType?->name,
                'charge_type_id' => $s->charge_type_id,
                'charge_type' => $s->chargeType?->name,
                'security_type_ids' => $s->securityTypes->modelKeys(),
                'security_types' => $s->securityTypes->pluck('name')->all(),
                'pertaining_to' => $s->pertaining_to,
                'is_encumbered' => $s->is_encumbered,
                'description' => $s->description,
                'address' => $s->address,
                'pincode' => $s->pincode,
                'city' => $s->city,
                'state_id' => $s->state_id,
                'state' => $s->state?->name,
                'form_of_securities' => $s->form_of_securities,
                'confirming_party' => $s->confirming_party,
                'registered' => $s->registrations->map(fn (SecurityRegistration $r) => $r->kind->label().' · '.$r->status->label($r->kind))->values(),
                'can_remove' => $s->registrations->isEmpty() && ! in_array($s->id, $withDiligence, true),
                'kinds' => array_map(fn (RegistrationKind $k) => $k->value, $s->nature->registrationKinds()),
            ]),
            'registrations' => $registrations->map(fn (SecurityRegistration $r) => [
                'id' => $r->id,
                'kind' => $r->kind->value,
                'kind_label' => $r->kind->label(),
                'status' => $r->status->value,
                'status_label' => $r->status->label($r->kind),
                'status_tone' => $r->status->tone(),
                'reference' => $r->reference,
                'reference_label' => $r->kind->referenceLabel(),
                'filing_label' => $r->kind->filingLabel(),
                'amount' => $r->amount,
                'pledge' => $r->kind === RegistrationKind::Pledge ? [
                    'security_name' => $r->security_name,
                    'quantity' => $r->quantity,
                    'face_value' => $r->face_value,
                    'depository' => $r->depository,
                    'pledgor' => trim(implode(' / ', array_filter([$r->pledgor_dp_id, $r->pledgor_client_id]))),
                    'pledgee' => trim(implode(' / ', array_filter([$r->pledgee_dp_id, $r->pledgee_client_id]))),
                ] : null,
                'securities' => $r->securities->map(fn (DealSecurity $s) => $s->summary())->values(),
                'events' => $r->events->map(fn (SecurityRegistrationEvent $e) => [
                    'id' => $e->id,
                    'action' => $e->action->value,
                    'action_label' => $e->action->label($r->kind),
                    'happened_on' => $e->happened_on->toDateString(),
                    'filing_reference' => $e->filing_reference,
                    'amount' => $e->amount,
                    'reason' => $e->reason,
                    'by' => $e->creator->name,
                    'files' => $e->files->map(fn (DocumentFile $f) => $f->present())->values(),
                ])->values(),
                'is_active' => $r->status === RegistrationStatus::Active,
            ]),
            'diligence' => $diligence->map(fn (DealDiligenceItem $d) => [
                'id' => $d->id,
                'kind' => $d->kind->value,
                'kind_label' => $d->kind->label(),
                'title' => $d->title,
                'security' => $d->security?->summary(),
                'asset_owner' => $d->asset_owner,
                'issued_by' => $d->agency?->name,
                'reference' => $d->reference,
                'status' => $d->status->value,
                'status_label' => $d->status->label(),
                'status_tone' => $d->status->tone(),
                'is_open' => $d->status->isOpen(),
                'submitted_by' => $d->submitter?->name,
                'submitted_at' => $d->submitted_at?->toIso8601String(),
                'checker' => $d->checker?->name,
                'checker_comment' => $d->checker_comment,
                'checked_at' => $d->checked_at?->toIso8601String(),
                'files' => $d->files->whereNull('removed_at')->sortBy('id')->map(fn (DocumentFile $f) => $f->present())->values(),
                'removed_files' => $d->files->whereNotNull('removed_at')->map(fn (DocumentFile $f) => $f->present())->values(),
                'can_check' => $d->status === ConditionStatus::Submitted && $d->submitted_by !== $user->id,
                'is_mine' => $d->submitted_by === $user->id,
                'can_remove' => $d->status === ConditionStatus::Pending && $d->files->isEmpty(),
            ]),
            'options' => [
                'documents' => $deal->dealDocuments()->with('type:id,security_nature')->orderBy('name')->get(['id', 'name', 'legal_document_type_id'])
                    ->map(fn (DealDocument $d) => ['value' => $d->id, 'label' => $d->name, 'nature' => $d->type->security_nature?->value]),
                'natures' => SecurityNature::options(),
                'owner_id_types' => OwnerIdType::options(),
                'asset_types' => AssetType::query()->active()->orderBy('name')->get(['id', 'name'])->map(fn (AssetType $a) => ['value' => $a->id, 'label' => $a->name]),
                'charge_types' => ChargeType::query()->active()->orderBy('name')->get(['id', 'name'])->map(fn (ChargeType $c) => ['value' => $c->id, 'label' => $c->name]),
                'security_types' => SecurityType::query()->active()->orderBy('name')->get(['id', 'name', 'asset_type_id'])
                    ->map(fn (SecurityType $t) => ['value' => $t->id, 'label' => $t->name, 'asset_type_id' => $t->asset_type_id]),
                'states' => State::query()->orderBy('name')->get(['id', 'name'])->map(fn (State $st) => ['value' => $st->id, 'label' => $st->name]),
                'kinds' => RegistrationKind::options(),
                'diligence_kinds' => DiligenceKind::options(),
                'agencies' => EmpanelledAgency::query()->active()->orderBy('name')->get(['id', 'code', 'name'])
                    ->map(fn (EmpanelledAgency $a) => ['value' => $a->id, 'label' => $a->name, 'description' => $a->code]),
                'asset_owners' => $securities->pluck('asset_owner')->unique()->sort()->values(),
            ],
            'active_registrations' => $registrations->where('status', RegistrationStatus::Active)->count(),
        ];
    }

    /**
     * The deal's ISINs with their allotments and schedules, and the form choices.
     *
     * @return array<string, mixed>
     */
    private function invoices(Transaction $deal): array
    {
        $invoices = $deal->invoices()->with(['transaction:id,ulid,el_number,company_id', 'transaction.company:id,name', 'creator:id,name'])
            ->orderByDesc('id')->get()
            // Drafts first, then newest invoice date first.
            ->sortBy(fn (Invoice $i) => [$i->invoice_date === null ? 0 : 1, -($i->invoice_date?->getTimestamp() ?? 0), -$i->id])->values();
        $byId = $invoices->keyBy('id');
        $queueUntil = today()->addDays((int) config('billing.queue_days'));
        $billedBy = fn (?int $invoiceId) => $invoiceId && $byId->has($invoiceId)
            ? ['id' => $byId[$invoiceId]->ulid, 'title' => $byId[$invoiceId]->title(), 'status_label' => $byId[$invoiceId]->status->label()]
            : null;

        return [
            'has_billing' => $deal->billing()->exists(),
            'balance_due' => (string) $invoices->filter(fn (Invoice $i) => $i->isCollectable())
                ->reduce(fn (BigDecimal $sum, Invoice $i) => $sum->plus($i->balance_due), BigDecimal::zero()->toScale(2)),
            'overdue' => $invoices->filter(fn (Invoice $i) => $i->isCollectable() && $i->isOverdue())->count(),
            'invoices' => $invoices->map(fn (Invoice $i) => InvoiceController::row($i))->values(),
            'periods' => FeeSchedulePeriod::query()->whereHas('feeLine', fn ($q) => $q->where('transaction_id', $deal->id))
                ->with('feeLine:id,kind')->orderBy('from_date')->orderBy('id')->get()
                ->map(fn (FeeSchedulePeriod $p) => [
                    'id' => $p->id,
                    'fee' => $p->feeLine->kind->label(),
                    'from' => $p->from_date->toDateString(),
                    'to' => $p->to_date->toDateString(),
                    'bill_date' => $p->bill_date->toDateString(),
                    'amount' => $p->amount,
                    'invoice' => $billedBy($p->invoice_id),
                    'billable' => $p->invoice_id === null,
                    'due' => $p->invoice_id === null && ! $p->bill_date->isAfter($queueUntil),
                ])->values(),
            'expenses' => $deal->expenses()->whereNull('removed_at')->with(['files', 'creator:id,name'])->orderByDesc('incurred_on')->orderByDesc('id')->get()
                ->map(fn (DealExpense $e) => [
                    'id' => $e->ulid,
                    'incurred_on' => $e->incurred_on?->toDateString(),
                    'description' => $e->description,
                    'amount' => $e->amount,
                    'recorded_by' => $e->creator->name,
                    'invoice' => $billedBy($e->invoice_id),
                    'files' => $e->files->map(fn (DocumentFile $f) => ['id' => $f->ulid, 'name' => $f->original_name, 'size' => $f->size, 'available' => $f->isAvailable()])->values(),
                ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function isin(Transaction $deal): array
    {
        $fileWith = ['uploader:id,name', 'remover:id,name'];
        $isins = $deal->isins()
            ->with([
                'allotments.files' => fn ($q) => $q->with($fileWith),
                'payments' => fn ($q) => $q->with(['recorder:id,name', 'files' => fn ($f) => $f->with($fileWith)])->withMax('reminders', 'sent_on'),
            ])
            ->orderBy('allotment_date')->orderBy('isin')->get();

        return [
            'isins' => $isins->map(function (DealIsin $i) {
                $open = $i->payments->where('status', IsinPaymentStatus::Due);
                $next = $open->first(fn (IsinPayment $p) => ! $p->due_on->isBefore(today()));

                return [
                    'id' => $i->ulid,
                    'isin' => $i->isin,
                    'series_name' => $i->series_name,
                    'values' => [
                        'isin' => $i->isin,
                        'series_name' => $i->series_name,
                        'listing' => $i->listing?->value,
                        'exchange' => $i->exchange,
                        'depository' => $i->depository,
                        'placement' => $i->placement?->value,
                        'allotment_date' => $i->allotment_date?->toDateString(),
                        'maturity_date' => $i->maturity_date?->toDateString(),
                        'coupon_type' => $i->coupon_type?->value,
                        'coupon_rate' => $i->coupon_rate,
                        'coupon_description' => $i->coupon_description,
                        'interest_frequency' => $i->interest_frequency?->value,
                        'principal_frequency' => $i->principal_frequency?->value,
                        'day_count' => $i->day_count?->value,
                        'holiday_convention' => $i->holiday_convention?->value,
                        'put_date' => $i->put_date?->toDateString(),
                        'call_date' => $i->call_date?->toDateString(),
                        'comments' => $i->comments,
                    ],
                    'facts' => array_filter([
                        'Listing' => trim(($i->listing?->label() ?? '').($i->exchange ? " · {$i->exchange}" : '')) ?: null,
                        'Depository' => $i->depository,
                        'Placement' => $i->placement?->label(),
                        'Coupon' => trim(($i->coupon_type?->label() ?? '').($i->coupon_rate !== null ? ' '.rtrim(rtrim($i->coupon_rate, '0'), '.').'%' : '').($i->coupon_description ? " · {$i->coupon_description}" : '')) ?: null,
                        'Interest' => $i->interest_frequency?->label(),
                        'Principal' => $i->principal_frequency?->label(),
                        'Day count' => $i->day_count?->label(),
                        'Weekends' => $i->holiday_convention?->label(),
                        'Put' => $i->put_date?->format('d M Y'),
                        'Call' => $i->call_date?->format('d M Y'),
                    ]),
                    'allotment_date' => $i->allotment_date?->toDateString(),
                    'maturity_date' => $i->maturity_date?->toDateString(),
                    'comments' => $i->comments,
                    'redeemed' => $i->isRedeemed(),
                    'allotted_quantity' => (int) $i->allotments->sum('quantity_allotted'),
                    'allotted_amount' => $i->allotments->reduce(fn (BigDecimal $sum, IsinAllotment $a) => $sum->plus($a->amount), BigDecimal::zero())->toScale(2)->__toString(),
                    'next_due' => $next ? ['kind' => $next->kind->label(), 'due_on' => $next->due_on->toDateString()] : null,
                    'overdue' => $open->filter(fn (IsinPayment $p) => $p->isOverdue())->count(),
                    'allotments' => $i->allotments->map(fn (IsinAllotment $a) => [
                        'id' => $a->id,
                        'kind_label' => $a->kind->label(),
                        'allotment_date' => $a->allotment_date->toDateString(),
                        'face_value' => $a->face_value,
                        'quantity_allotted' => $a->quantity_allotted,
                        'quantity_offered' => $a->quantity_offered,
                        'amount' => $a->amount,
                        'credit' => $a->credit_depository ? trim($a->credit_depository.($a->credited_on ? ' · '.$a->credited_on->format('d M Y') : '')) : null,
                        'files' => $a->files->map(fn (DocumentFile $f) => $f->present())->values(),
                    ])->values(),
                    'payments' => $i->payments->map(fn (IsinPayment $p) => [
                        'id' => $p->id,
                        'kind' => $p->kind->value,
                        'kind_label' => $p->kind->label(),
                        'due_on' => $p->due_on->toDateString(),
                        'original_due_on' => $p->original_due_on?->toDateString(),
                        'due_date_reason' => $p->due_date_reason,
                        'status' => $p->status->value,
                        'status_label' => $p->status->label(),
                        'status_tone' => $p->isOverdue() ? 'danger' : $p->status->tone(),
                        'overdue' => $p->isOverdue(),
                        'paid_on' => $p->paid_on?->toDateString(),
                        'amount' => $p->amount,
                        'face_value' => $p->face_value,
                        'quantity' => $p->quantity,
                        'redemption_basis' => $p->redemption_basis?->label(),
                        'remark' => $p->remark,
                        'recorded_by' => $p->recorder?->name,
                        'files' => $p->files->map(fn (DocumentFile $f) => $f->present())->values(),
                        'last_reminded' => $p->getAttribute('reminders_max_sent_on'),
                    ])->values(),
                ];
            }),
            'options' => [
                'listings' => Listing::options(),
                'placements' => Placement::options(),
                'coupon_types' => CouponType::options(),
                'frequencies' => PaymentFrequency::options(),
                'day_counts' => DayCount::options(),
                'holiday_conventions' => HolidayConvention::options(),
                'kinds' => IsinPaymentKind::options(),
                'outcomes' => array_values(array_filter(IsinPaymentStatus::options(), fn (array $o) => $o['value'] !== IsinPaymentStatus::Due->value)),
                'redemption_bases' => RedemptionBasis::options(),
                'allotment_kinds' => AllotmentKind::options(),
            ],
            'reminder_days' => (int) config('isin.reminder_days'),
            'reminder_overdue_days' => (int) config('isin.reminder_overdue_days'),
        ];
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
            DealDocument::class => $deal->dealDocuments()->withTrashed()->pluck('id')->all(),
            DealCondition::class => $deal->conditions()->pluck('id')->all(),
            DealExecution::class => $deal->executions()->pluck('id')->all(),
            DealSecurity::class => $deal->securities()->withTrashed()->pluck('id')->all(),
            SecurityRegistration::class => $deal->registrations()->pluck('id')->all(),
            DealDiligenceItem::class => $deal->diligenceItems()->pluck('id')->all(),
            DealIsin::class => $deal->isins()->pluck('id')->all(),
            IsinPayment::class => IsinPayment::query()->whereIn('deal_isin_id', $deal->isins()->pluck('id'))->pluck('id')->all(),
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
                    DealDocument::class => 'Documents',
                    DealCondition::class => 'CP/CS',
                    DealExecution::class => 'Execution',
                    DealSecurity::class, SecurityRegistration::class => 'Security',
                    DealDiligenceItem::class => 'Due diligence',
                    DealIsin::class, IsinPayment::class => 'ISIN',
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
