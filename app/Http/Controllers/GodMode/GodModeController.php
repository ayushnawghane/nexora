<?php

namespace App\Http\Controllers\GodMode;

use App\Actions\GodMode\CorrectEngagementLetter;
use App\Actions\GodMode\MakeCorrection;
use App\Actions\GodMode\RollBackCorrection;
use App\Actions\Transactions\VerifySchedule;
use App\Enums\FeeStartReference;
use App\Enums\IsinPaymentStatus;
use App\Enums\TransactionStatus;
use App\GodMode\Editors;
use App\Http\Controllers\Controller;
use App\Http\Requests\GodMode\CorrectionRequest;
use App\Http\Requests\GodMode\GodModeRequest;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\CompanyContact;
use App\Models\CompanyGstin;
use App\Models\DealCondition;
use App\Models\DealDiligenceItem;
use App\Models\DealDocument;
use App\Models\DealExecution;
use App\Models\DealExpense;
use App\Models\DealIsin;
use App\Models\DealJobSheetEntry;
use App\Models\DealSecurity;
use App\Models\EngagementLetter;
use App\Models\FeeLine;
use App\Models\GodModeChange;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoiceReceipt;
use App\Models\IsinPayment;
use App\Models\RetiredElNumber;
use App\Models\SecurityRegistration;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * God Mode: find any business record, see a company's or a deal's whole record tree, correct any part
 * of it with a reason, and undo corrections. Super-admins only, with a 2FA code from the last 15
 * minutes (enforced by the route group).
 */
class GodModeController extends Controller
{
    public function index(Request $request): Response
    {
        $term = trim((string) $request->query('q'));

        return Inertia::render('GodMode/Index', [
            'q' => $term,
            'results' => mb_strlen($term) >= 2 ? $this->search($term) : null,
            'recent' => $this->history(GodModeChange::query()->latest('id')->limit(30)),
        ]);
    }

    public function company(Company $company): Response
    {
        $company->load(['gstins', 'addresses.state', 'contacts', 'transactions' => fn ($q) => $q->latest('id')]);
        $item = fn (string $editor, $record, string $title, ?string $subtitle = null, bool $active = true) => [
            ...Editors::present(Editors::get($editor), $record, (string) $record->getKey()),
            'title' => $title,
            'subtitle' => $subtitle,
            'inactive' => ! $active,
        ];

        return Inertia::render('GodMode/Record', [
            'record' => ['kind' => 'company', 'title' => $company->name, 'subtitle' => $company->cin ?? $company->pan],
            'sections' => [
                ['title' => 'Company', 'items' => [$item('company', $company, $company->name, $company->cin)]],
                ['title' => 'GSTINs', 'items' => $company->gstins->map(fn (CompanyGstin $g) => $item('company-gstin', $g, $g->gstin, null, $g->is_active))->values()],
                ['title' => 'Addresses', 'items' => $company->addresses->map(fn (CompanyAddress $a) => $item('company-address', $a, $a->type->label(), "{$a->line1}, {$a->city} {$a->pincode}", $a->is_active))->values()],
                ['title' => 'Contacts', 'items' => $company->contacts->map(fn (CompanyContact $c) => $item('company-contact', $c, $c->name, $c->email ?? $c->mobile, $c->is_active))->values()],
            ],
            'links' => $company->transactions->map(fn (Transaction $t) => [
                'title' => $t->el_number ?? 'Transaction without EL',
                'subtitle' => $t->deal_status?->label() ?? $t->status->label(),
                'href' => route('god-mode.transactions', $t->ulid),
            ])->values(),
            'history' => $this->history(GodModeChange::query()->where('company_id', $company->id)->latest('id')),
        ]);
    }

    public function transaction(Transaction $transaction): Response
    {
        $transaction->load(['company:id,ulid,name', 'jobSheetEntries.activity:id,name', 'dealDocuments', 'conditions', 'executions.document', 'securities.securityTypes', 'registrations', 'diligenceItems', 'isins.payments', 'invoices.lines', 'invoices.receipts']);
        $item = fn (string $editor, $record, string $title, ?string $subtitle = null, ?string $id = null) => [
            ...Editors::present(Editors::get($editor), $record, $id ?? (string) $record->getKey()),
            'title' => $title,
            'subtitle' => $subtitle,
            'inactive' => false,
        ];
        $id = $transaction->ulid;

        $sections = [['title' => 'Transaction', 'items' => [
            $item('transaction-basics', $transaction, 'Basics', null, $id),
            $item('transaction-contacts', $transaction, 'Letter contacts', null, $id),
            $item('issue-details', $transaction, 'Issue details', null, $id),
            $item('fees', $transaction, 'Fees', 'Saving rebuilds the schedule', $id),
        ]]];

        if ($transaction->isDeal()) {
            $deal = [$item('deal-billing', $transaction, 'Billing', null, $id)];
            if ($transaction->isOpenDeal()) {
                $deal[] = $item('deal-status', $transaction, 'Force a status change', "Now {$transaction->deal_status?->label()}", $id);
            }
            $sections[] = ['title' => 'Deal', 'items' => $deal];
            $sections[] = ['title' => 'Job sheet', 'items' => $transaction->jobSheetEntries
                ->map(fn (DealJobSheetEntry $e) => $item('job-sheet-entry', $e, $e->activity->name, $e->status->label()))->values()];
            $sections[] = ['title' => 'Legal documents', 'items' => $transaction->dealDocuments
                ->map(fn (DealDocument $d) => $item('deal-document', $d, $d->name, $d->kind->label()))->values()];
            $sections[] = ['title' => 'CP / CS', 'items' => $transaction->conditions
                ->map(fn (DealCondition $c) => $item('deal-condition', $c, $c->name, "{$c->stage->short()} · {$c->status->label()}"))->values()];
            $sections[] = ['title' => 'Execution', 'items' => $transaction->executions
                ->map(fn (DealExecution $e) => $item('deal-execution', $e, $e->document->name, $e->status->label()))->values()];
            $sections[] = ['title' => 'Security', 'items' => $transaction->securities
                ->map(fn (DealSecurity $s) => $item('deal-security', $s, $s->summary(), $s->nature->label()))
                ->concat($transaction->registrations->map(fn (SecurityRegistration $r) => $item('security-registration', $r,
                    $r->kind->label().($r->reference ? " {$r->reference}" : ''), $r->status->label($r->kind))))
                ->values()];
            // Payments that are settled or due within 90 days (a long schedule would swamp the page).
            $sections[] = ['title' => 'ISINs', 'items' => $transaction->isins
                ->flatMap(fn (DealIsin $i) => collect([$item('deal-isin', $i, $i->isin, $i->series_name ? Str::limit($i->series_name, 60) : null)])
                    ->concat($i->payments
                        ->filter(fn (IsinPayment $p) => $p->status !== IsinPaymentStatus::Due || $p->due_on->isBefore(today()->addDays(90)))
                        ->map(fn (IsinPayment $p) => $item('isin-payment', $p, "{$i->isin} · {$p->kind->label()} {$p->due_on->format('d M Y')}", $p->status->label()))))
                ->values()];
            $sections[] = ['title' => 'Invoices', 'items' => $transaction->invoices->sortByDesc('id')
                ->flatMap(fn (Invoice $inv) => collect([$item('invoice', $inv, $inv->title(), "{$inv->status->label()} · ".Money::format($inv->total))])
                    ->concat($inv->lines->map(fn (InvoiceLine $l) => $item('invoice-line', $l, ($inv->number ?? 'Draft')." · {$l->description}", Money::format($l->amount))))
                    ->concat($inv->receipts->map(fn (InvoiceReceipt $r) => $item('invoice-receipt', $r, "{$inv->number} · receipt {$r->received_on->format('d M Y')}", Money::format($r->amount).($r->reversed_at ? ' · reversed' : '')))))
                ->concat($transaction->expenses()->whereNull('removed_at')->get()->map(fn (DealExpense $e) => $item('deal-expense', $e, "Expense · {$e->description}", Money::format($e->amount))))
                ->values()];
            $sections[] = ['title' => 'Due diligence', 'items' => $transaction->diligenceItems
                ->map(fn (DealDiligenceItem $d) => $item('diligence-item', $d, $d->title, "{$d->kind->label()} · {$d->status->label()}"))->values()];
        }

        $latest = $transaction->engagementLetters()->with('generator:id,name')->get();
        /** @var EngagementLetter|null $current */
        $current = $latest->first();

        return Inertia::render('GodMode/Record', [
            'record' => [
                'kind' => 'transaction',
                'id' => $id,
                'title' => $transaction->company->name,
                'subtitle' => $transaction->el_number ?? $transaction->status->label(),
                'status' => $transaction->deal_status?->label() ?? $transaction->status->label(),
                'company_href' => route('god-mode.companies', $transaction->company->ulid),
                'deal_href' => $transaction->isDeal() ? route('deals.show', $id) : route('transactions.show', $id),
            ],
            'sections' => $sections,
            'links' => [],
            'prompts' => [
                'verify_schedule' => $transaction->status !== TransactionStatus::Draft && ! $transaction->isScheduleVerified()
                    && $transaction->schedulePeriods()->exists(),
                'letter_outdated' => $current !== null && GodModeChange::query()
                    ->whereIn('editor', Editors::AFFECT_LETTER)
                    ->where(fn (Builder $q) => $q->where('transaction_id', $transaction->id)->orWhere('company_id', $transaction->company_id))
                    ->where('created_at', '>', $current->created_at)
                    ->exists(),
            ],
            'letter' => $current ? [
                'el_number' => $transaction->el_number,
                'el_date' => $transaction->el_date?->toDateString(),
                'min_date' => $transaction->approved_at?->toDateString(),
                'max_date' => today()->toDateString(),
                'fixed_date' => $transaction->feeLines()->get()->first(fn (FeeLine $f) => $f->start_reference === FeeStartReference::ElDate)?->start_date->toDateString(),
                'document' => $document = app(CorrectEngagementLetter::class)->documentOf($transaction, $current),
                'body' => CorrectEngagementLetter::bodyOf($document),
                'retired' => RetiredElNumber::query()->where('transaction_id', $transaction->id)->pluck('el_number'),
                'versions' => $latest->map(fn (EngagementLetter $l) => [
                    'version' => $l->version,
                    'el_number' => $l->el_number,
                    'el_date' => $l->el_date->toDateString(),
                    'reason' => $l->reason,
                    'generated_by' => $l->generator->name,
                    'generated_at' => $l->created_at?->toIso8601String(),
                    'href' => route('transactions.letter.download', [$id, $l->version]),
                    'has_pdf' => $l->hasPdf(),
                ]),
            ] : null,
            'history' => $this->history(GodModeChange::query()
                ->where(fn (Builder $q) => $q->where('transaction_id', $transaction->id)->orWhere('company_id', $transaction->company_id))
                ->latest('id')),
        ]);
    }

    public function correct(CorrectionRequest $request, string $editor, string $id, MakeCorrection $correct): RedirectResponse
    {
        $handler = Editors::get($editor);
        $record = $handler->find($id);

        $correct->handle($handler, $record, (array) $request->validated('values'), $request->validated('reason'), $request->validated('fingerprint'), $request->user());

        return back()->with('success', "{$handler->label()} corrected. The change is logged and can be undone from the history.");
    }

    public function rollback(GodModeRequest $request, GodModeChange $change, RollBackCorrection $rollback): RedirectResponse
    {
        $rollback->handle($change, $request->validated('reason'), $request->user());

        return back()->with('success', 'Correction undone. The undo is logged too.');
    }

    public function verifySchedule(GodModeRequest $request, Transaction $transaction, VerifySchedule $verify): RedirectResponse
    {
        DB::transaction(function () use ($request, $transaction, $verify) {
            $before = ['schedule_verified_at' => $transaction->schedule_verified_at?->toIso8601String()];
            $verify->handle($transaction, $request->user());

            GodModeChange::query()->create([
                'user_id' => $request->user()->id, 'editor' => 'schedule-verify',
                'subject_type' => Transaction::class, 'subject_id' => $transaction->id,
                'transaction_id' => $transaction->id, 'company_id' => $transaction->company_id,
                'reason' => $request->validated('reason'), 'before' => $before,
                'after' => ['schedule_verified_at' => $transaction->refresh()->schedule_verified_at?->toIso8601String()],
                'can_roll_back' => false,
            ]);
        });

        return back()->with('success', 'Schedule verified.');
    }

    /**
     * @return array{companies: list<array<string, mixed>>, transactions: list<array<string, mixed>>}
     */
    private function search(string $term): array
    {
        $like = '%'.$term.'%';
        $retired = RetiredElNumber::query()->where('el_number', 'like', $like)->pluck('transaction_id');

        return [
            'companies' => Company::query()->search($term)->orderBy('name')->limit(15)->get()
                ->map(fn (Company $c) => [
                    'title' => $c->name,
                    'subtitle' => $c->cin ?? $c->pan,
                    'inactive' => ! $c->is_active,
                    'href' => route('god-mode.companies', $c->ulid),
                ])->values()->all(),
            'transactions' => Transaction::query()
                ->where(fn (Builder $q) => $q->where('el_number', 'like', $like)->orWhere('deal_code', 'like', $like)
                    ->orWhereIn('id', $retired)
                    ->orWhereHas('company', fn (Builder $c) => $c->where('name', 'like', $like)))
                ->with('company:id,name')->latest('id')->limit(15)->get()
                ->map(fn (Transaction $t) => [
                    'title' => $t->company->name,
                    'subtitle' => trim(($t->el_number ?? 'No EL yet').' · '.($t->deal_status?->label() ?? $t->status->label())),
                    'inactive' => false,
                    'href' => route('god-mode.transactions', $t->ulid),
                ])->values()->all(),
        ];
    }

    /**
     * @param  Builder<GodModeChange>  $query
     * @return list<array<string, mixed>>
     */
    private function history(Builder $query): array
    {
        return $query->with(['user:id,name', 'revertedBy:id,reverts_change_id'])->limit(100)->get()
            ->map(fn (GodModeChange $c) => [
                'id' => $c->ulid,
                'editor' => $c->editor,
                'what' => $this->editorLabel($c->editor),
                'by' => $c->user->name,
                'at' => $c->created_at->toIso8601String(),
                'reason' => $c->reason,
                'diff' => $this->diff($c->before, $c->after),
                'is_undo' => $c->reverts_change_id !== null,
                'undone' => $c->revertedBy !== null,
                'can_undo' => $c->can_roll_back && $c->reverts_change_id === null && $c->revertedBy === null,
            ])->values()->all();
    }

    private function editorLabel(string $key): string
    {
        return match ($key) {
            'letter-regenerate' => 'Letter regenerated',
            'letter-wording' => 'Letter wording',
            'letter-number' => 'EL number / date',
            'letter-pdf' => 'Letter PDF replaced',
            'schedule-verify' => 'Schedule verified',
            default => Editors::get($key)->label(),
        };
    }

    /**
     * Field-by-field differences, with nested values flattened to dotted keys.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<array{field: string, before: mixed, after: mixed}>
     */
    private function diff(array $before, array $after): array
    {
        $old = Arr::dot($before);
        $new = Arr::dot($after);
        $rows = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            $a = $old[$key] ?? null;
            $b = $new[$key] ?? null;
            if ($a !== $b && ! ($a === [] && $b === null) && ! ($a === null && $b === [])) {
                $rows[] = ['field' => (string) $key, 'before' => $a, 'after' => $b];
            }
        }

        return $rows;
    }
}
