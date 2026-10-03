<?php

namespace App\Http\Controllers\Transactions;

use App\Actions\Transactions\SaveFees;
use App\Actions\Transactions\SaveIssueDetails;
use App\Actions\Transactions\SaveTransactionBasics;
use App\Actions\Transactions\SyncTransactionContacts;
use App\Actions\Transactions\VerifySchedule;
use App\Enums\EscalationType;
use App\Enums\FeeAmountType;
use App\Enums\FeeBasis;
use App\Enums\FeeFrequency;
use App\Enums\FeeKind;
use App\Enums\FeeStartReference;
use App\Enums\FeeTiming;
use App\Enums\Instrument;
use App\Enums\IssueType;
use App\Enums\Listing;
use App\Enums\Origin;
use App\Enums\Recipient;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\TransactionBasicsRequest;
use App\Http\Requests\Transactions\TransactionContactsRequest;
use App\Http\Requests\Transactions\TransactionFeesRequest;
use App\Http\Requests\Transactions\TransactionIssueRequest;
use App\Models\ApprovalRequest;
use App\Models\ApprovalVote;
use App\Models\Arranger;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\EngagementLetter;
use App\Models\FeeLine;
use App\Models\FeeSchedulePeriod;
use App\Models\LeadSource;
use App\Models\Transaction;
use App\Models\TransactionContact;
use App\Models\TransactionInstrument;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\VerticalTeam;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The DT transaction wizard: basics → contacts → issue → fees → schedule → review.
 * Each step saves on its own; a step opens once the steps before it are complete.
 */
class TransactionWizardController extends Controller
{
    public const STEPS = ['basics', 'contacts', 'issue', 'fees', 'schedule', 'review'];

    public function create(): Response
    {
        $this->authorize('create', Transaction::class);

        return Inertia::render('Transactions/Wizard', [
            'transaction' => null,
            'step' => 'basics',
            'progress' => array_fill_keys(self::STEPS, false),
            'options' => $this->options(null),
        ]);
    }

    public function store(TransactionBasicsRequest $request, SaveTransactionBasics $save): RedirectResponse
    {
        $transaction = $save->handle($request->validated(), $request->user());

        return redirect()->route('transactions.edit', [$transaction, 'step' => 'contacts'])->with('success', 'Draft created.');
    }

    public function edit(Request $request, Transaction $transaction): Response|RedirectResponse
    {
        $this->authorize('view', $transaction);

        if (! $request->user()->can('update', $transaction)) {
            return redirect()->route('transactions.show', $transaction);
        }

        $transaction->load([
            'company:id,ulid,name,cin,pan',
            'issueDetail',
            'instruments',
            'contacts.companyContact.contactType:id,name',
            'feeLines.periods',
            'scheduleVerifier:id,name',
        ]);

        $progress = $this->progress($transaction);
        $requested = (string) $request->query('step', '');
        $step = in_array($requested, self::STEPS, true) && $this->reachable($progress, $requested)
            ? $requested
            : $this->firstIncomplete($progress);

        return Inertia::render('Transactions/Wizard', [
            'transaction' => $this->present($transaction),
            'step' => $step,
            'progress' => $progress,
            'options' => $this->options($transaction),
            'can' => ['submit' => $request->user()->can('submit', $transaction)],
        ]);
    }

    /** Read-only view of any transaction (submitted ones can't be edited through the wizard). */
    public function show(Request $request, Transaction $transaction): Response
    {
        $this->authorize('view', $transaction);

        $transaction->load([
            'company:id,ulid,name,cin,pan',
            'issueDetail',
            'instruments',
            'contacts.companyContact.contactType:id,name',
            'feeLines.periods',
            'scheduleVerifier:id,name',
            'approvalRequests' => fn ($q) => $q->latest('id')->with(['requester:id,name', 'votes' => fn ($v) => $v->oldest('id')->with('user:id,name')]),
        ]);

        $user = $request->user();
        $open = $transaction->approvalRequests->first(fn (ApprovalRequest $r) => $r->isOpen());

        return Inertia::render('Transactions/Show', [
            'transaction' => $this->present($transaction),
            'options' => $this->options($transaction),
            'approvals' => $transaction->approvalRequests->map(fn (ApprovalRequest $r) => [
                'id' => $r->ulid,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'requested_by' => $r->requester->name,
                'requested_at' => $r->created_at?->toIso8601String(),
                'closed_at' => $r->closed_at?->toIso8601String(),
                'votes' => $r->votes->map(fn (ApprovalVote $v) => [
                    'user' => $v->user->name,
                    'decision' => $v->decision->value,
                    'decision_label' => $v->decision->label(),
                    'is_head' => $v->is_head,
                    'comment' => $v->comment,
                    'via' => $v->via,
                    'at' => $v->created_at?->toIso8601String(),
                ]),
            ]),
            'can' => [
                'update' => $user->can('update', $transaction),
                'vote' => $open !== null && $user->can('approvals.vote') && $open->requested_by !== $user->id
                    && $open->votes->doesntContain('user_id', $user->id),
                'revise' => $transaction->status === TransactionStatus::Rejected && $user->can('create', Transaction::class),
            ],
            'openRequestId' => $open?->ulid,
            'letters' => $transaction->engagementLetters()->with('generator:id,name')->get()->map(fn (EngagementLetter $l) => [
                'version' => $l->version,
                'el_number' => $l->el_number,
                'el_date' => $l->el_date->toDateString(),
                'reason' => $l->reason,
                'generated_by' => $l->generator->name,
                'generated_at' => $l->created_at?->toIso8601String(),
            ]),
            'letterIssue' => $transaction->status === TransactionStatus::Approved && $user->can('transactions.issue_el') ? [
                // A fee that runs from the EL date fixes the date the letter must carry.
                'fixed_date' => $transaction->feeLines->first(fn (FeeLine $f) => $f->start_reference === FeeStartReference::ElDate)?->start_date->toDateString(),
                'min_date' => $transaction->approved_at?->toDateString(),
                'max_date' => today()->toDateString(),
            ] : null,
        ]);
    }

    public function updateBasics(TransactionBasicsRequest $request, Transaction $transaction, SaveTransactionBasics $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user(), $transaction);

        return $this->next($transaction, 'contacts', 'Basics saved.');
    }

    public function updateContacts(TransactionContactsRequest $request, Transaction $transaction, SyncTransactionContacts $sync): RedirectResponse
    {
        $sync->handle($transaction, $request->validated('contacts'), $request->user());

        return $this->next($transaction, 'issue', 'Contacts saved.');
    }

    public function updateIssue(TransactionIssueRequest $request, Transaction $transaction, SaveIssueDetails $save): RedirectResponse
    {
        $save->handle($transaction, $request->validated(), $request->user());

        return $this->next($transaction, 'fees', 'Issue details saved.');
    }

    public function updateFees(TransactionFeesRequest $request, Transaction $transaction, SaveFees $save): RedirectResponse
    {
        $save->handle($transaction, $request->validated('fees'), $request->user());

        return $this->next($transaction, 'schedule', 'Fees saved. Check the schedule and verify it.');
    }

    public function verifySchedule(Request $request, Transaction $transaction, VerifySchedule $verify): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $verify->handle($transaction, $request->user());

        return $this->next($transaction, 'review', 'Schedule verified.');
    }

    private function next(Transaction $transaction, string $step, string $message): RedirectResponse
    {
        return redirect()->route('transactions.edit', [$transaction, 'step' => $step])->with('success', $message);
    }

    /**
     * @return array<string, bool>
     */
    private function progress(Transaction $transaction): array
    {
        $basics = $transaction->exists;
        $contacts = $transaction->contacts->contains(fn (TransactionContact $c) => $c->recipient === Recipient::To);
        $issue = $transaction->issueDetail !== null;
        $fees = $transaction->feeLines->isNotEmpty();
        $schedule = $fees && $transaction->isScheduleVerified();

        return [
            'basics' => $basics,
            'contacts' => $contacts,
            'issue' => $issue,
            'fees' => $fees,
            'schedule' => $schedule,
            'review' => $basics && $contacts && $issue && $fees && $schedule,
        ];
    }

    /**
     * @param  array<string, bool>  $progress
     */
    private function reachable(array $progress, string $step): bool
    {
        foreach (self::STEPS as $s) {
            if ($s === $step) {
                return true;
            }
            if (! $progress[$s]) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param  array<string, bool>  $progress
     */
    private function firstIncomplete(array $progress): string
    {
        foreach (self::STEPS as $s) {
            if (! $progress[$s]) {
                return $s;
            }
        }

        return 'review';
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Transaction $transaction): array
    {
        $issue = $transaction->issueDetail;

        return [
            'id' => $transaction->ulid,
            'status' => $transaction->status->value,
            'status_label' => $transaction->status->label(),
            'el_number' => $transaction->el_number,
            'company' => [
                'id' => $transaction->company->ulid,
                'name' => $transaction->company->name,
                'cin' => $transaction->company->cin,
            ],
            'basics' => [
                'company_id' => $transaction->company_id,
                'transaction_type_id' => $transaction->transaction_type_id,
                'lead_source_id' => $transaction->lead_source_id,
                'arranger_id' => $transaction->arranger_id,
                'vertical_team_id' => $transaction->vertical_team_id,
                'relationship_manager_id' => $transaction->relationship_manager_id,
                'signatory_id' => $transaction->signatory_id,
                'origin' => $transaction->origin->value,
                'brief' => $transaction->brief,
            ],
            'contacts' => $transaction->contacts->map(fn (TransactionContact $c) => [
                'company_contact_id' => $c->company_contact_id,
                'recipient' => $c->recipient->value,
            ])->values(),
            'issue' => $issue ? [
                'listing' => $issue->listing->value,
                'issue_type' => $issue->issue_type->value,
                'is_secured' => $issue->is_secured,
                'is_rated' => $issue->is_rated,
                'base_issue_size' => $issue->base_issue_size,
                'green_shoe_size' => $issue->green_shoe_size,
                'total_issue_size' => $issue->total_issue_size,
                'tenure_months' => $issue->tenure_months,
                'tenure_days' => $issue->tenure_days,
                'instruments' => $transaction->instruments->map(fn (TransactionInstrument $i) => [
                    'instrument' => $i->instrument->value,
                    'base_amount' => $i->base_amount,
                    'green_shoe_amount' => $i->green_shoe_amount,
                ])->values(),
            ] : null,
            'fees' => $transaction->feeLines->mapWithKeys(fn (FeeLine $f) => [$f->kind->value => [
                'enabled' => true,
                'amount_type' => $f->amount_type->value,
                'amount' => $f->amount,
                'percent' => $f->percent,
                'annual_amount' => $f->annual_amount,
                'basis' => $f->basis->value,
                'frequency' => $f->frequency->value,
                'start_reference' => $f->start_reference->value,
                'start_date' => $f->start_date->toDateString(),
                'timing' => $f->timing->value,
                'escalation_type' => $f->escalation_type->value,
                'escalation_value' => $f->escalation_value,
                'escalation_every_years' => $f->escalation_every_years,
            ]]),
            'schedule' => [
                'verified_at' => $transaction->schedule_verified_at?->toIso8601String(),
                'verified_by' => $transaction->scheduleVerifier?->name,
                'lines' => $transaction->feeLines->map(fn (FeeLine $f) => [
                    'kind' => $f->kind->value,
                    'label' => $f->kind->label(),
                    'frequency' => $f->frequency->label(),
                    'timing' => $f->timing->label(),
                    'annual_amount' => $f->annual_amount,
                    'total' => (string) $f->periods->reduce(fn (BigDecimal $sum, FeeSchedulePeriod $p) => $sum->plus($p->amount), BigDecimal::zero())->toScale(2),
                    'periods' => $f->periods->map(fn (FeeSchedulePeriod $p) => [
                        'sequence' => $p->sequence,
                        'financial_year' => $p->financial_year,
                        'from_date' => $p->from_date->toDateString(),
                        'to_date' => $p->to_date->toDateString(),
                        'bill_date' => $p->bill_date->toDateString(),
                        'days' => $p->days,
                        'days_in_year' => $p->days_in_year,
                        'base_amount' => $p->base_amount,
                        'amount' => $p->amount,
                        'prorated' => $p->prorated,
                    ])->values(),
                ])->values(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(?Transaction $transaction): array
    {
        $pick = fn ($query, ?int $current) => $query->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $current ?? 0));

        return [
            'companies' => $pick(Company::query(), $transaction?->company_id)->orderBy('name')->get(['id', 'name', 'cin'])
                ->map(fn (Company $c) => ['value' => $c->id, 'label' => $c->name, 'description' => $c->cin]),
            'transactionTypes' => $pick(TransactionType::query(), $transaction?->transaction_type_id)->orderBy('name')->get(['id', 'name']),
            'leadSources' => $pick(LeadSource::query(), $transaction?->lead_source_id)->orderBy('name')->get(['id', 'name']),
            'arrangers' => $pick(Arranger::query(), $transaction?->arranger_id)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Arranger $a) => ['value' => $a->id, 'label' => $a->name]),
            'verticalTeams' => $pick(VerticalTeam::query(), $transaction?->vertical_team_id)->with('vertical:id,name')->orderBy('name')->get(['id', 'name', 'vertical_id'])
                ->map(fn (VerticalTeam $t) => ['id' => $t->id, 'name' => $t->vertical ? "{$t->vertical->name} · {$t->name}" : $t->name]),
            'users' => $pick(User::query(), $transaction?->relationship_manager_id)->orderBy('name')->get(['id', 'name', 'emp_code'])
                ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name, 'description' => $u->emp_code]),
            'signatories' => $pick(User::query()->where('is_authorised_signatory', true), $transaction?->signatory_id)->orderBy('name')->get(['id', 'name', 'emp_code'])
                ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name, 'description' => $u->emp_code]),
            'contacts' => $transaction
                ? CompanyContact::query()->where('company_id', $transaction->company_id)
                    ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $transaction->contacts->pluck('company_contact_id')->all() ?: [0]))
                    ->with('contactType:id,name')->orderBy('name')->get()
                    ->map(fn (CompanyContact $c) => [
                        'id' => $c->id,
                        'name' => trim("{$c->salutation} {$c->name}"),
                        'designation' => $c->designation,
                        'email' => $c->email,
                        'mobile' => $c->mobile,
                        'type' => $c->contactType?->name,
                    ])
                : [],
            'origins' => Origin::options(),
            'recipients' => Recipient::options(),
            'listings' => Listing::options(),
            'issueTypes' => IssueType::options(),
            'instruments' => Instrument::options(),
            'fee' => [
                'kinds' => array_map(fn (FeeKind $k) => [
                    'value' => $k->value,
                    'label' => $k->label(),
                    'frequencies' => array_map(fn (FeeFrequency $f) => ['value' => $f->value, 'label' => $f->label()], $k->allowedFrequencies()),
                ], FeeKind::cases()),
                'amountTypes' => FeeAmountType::options(),
                'bases' => FeeBasis::options(),
                'startReferences' => FeeStartReference::options(),
                'timings' => FeeTiming::options(),
                'escalationTypes' => EscalationType::options(),
            ],
        ];
    }
}
