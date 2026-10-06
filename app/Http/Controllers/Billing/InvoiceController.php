<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\DraftInvoice;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordReceipt;
use App\Enums\InvoiceKind;
use App\Enums\InvoiceLineKind;
use App\Enums\InvoiceStatus;
use App\Exports\InvoicesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CreditNoteRequest;
use App\Http\Requests\Billing\DraftInvoiceRequest;
use App\Http\Requests\Billing\ReasonRequest;
use App\Http\Requests\Billing\ReceiptRequest;
use App\Models\DealExpense;
use App\Models\FeeSchedulePeriod;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoiceMail;
use App\Models\InvoiceReceipt;
use App\Services\Billing\InvoiceRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** The invoices hub (all deals), one invoice's page, and what can be done to it. */
class InvoiceController extends Controller
{
    /** The hub's tabs. */
    private const TABS = ['drafts', 'proforma', 'tax', 'credit_note', 'reimbursement', 'due', 'cancelled'];

    public function index(Request $request): Response
    {
        $this->authorize('billing.view');
        $tab = self::tabFrom($request);

        $invoices = QueryBuilder::for(self::tab(self::query(), $tab))
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $q, mixed $value) => self::search($q, (string) $value)),
                AllowedFilter::callback('tab', fn () => null), // applied above
            )
            ->allowedSorts('invoice_date', 'total', 'balance_due', 'created_at')
            ->defaultSort($tab === 'drafts' ? '-created_at' : '-invoice_date')
            ->paginate($request->integer('per_page', 25) > 0 ? min($request->integer('per_page', 25), 100) : 25)
            ->withQueryString()
            ->through(fn (Invoice $i) => self::row($i));

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'counts' => [
                'drafts' => Invoice::query()->where('status', InvoiceStatus::Draft)->count(),
                'due' => Invoice::query()->due()->count(),
                'overdue' => Invoice::query()->overdue()->count(),
            ],
            'filters' => ['tab' => $tab, 'search' => $request->input('filter.search', '')],
            'sort' => $request->input('sort', $tab === 'drafts' ? '-created_at' : '-invoice_date'),
            'exportUrl' => route('invoices.export', ['filter' => array_filter(['tab' => $tab, 'search' => $request->input('filter.search')])]),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('billing.view');
        $tab = self::tabFrom($request);
        $query = self::tab(self::query(), $tab)->orderBy('invoice_date')->orderBy('id');
        self::search($query, trim((string) $request->input('filter.search')));

        return Excel::download(new InvoicesExport($query), "invoices-{$tab}-".now()->format('Y-m-d').'.xlsx');
    }

    public function show(Request $request, Invoice $invoice, DraftInvoice $drafts): Response
    {
        $this->authorize('billing.view');
        $user = $request->user();
        $invoice->load([
            'transaction:id,ulid,el_number,deal_code,company_id', 'transaction.company:id,ulid,name',
            'lines.expense:id,ulid', 'placeOfSupply:id,name', 'parent:id,ulid,kind,number,status,invoice_date',
            'children' => fn ($q) => $q->latest('id'),
            'receipts' => fn ($q) => $q->with(['creator:id,name', 'reverser:id,name']),
            'mails' => fn ($q) => $q->with('sender:id,name'),
            'creator:id,name', 'issuer:id,name', 'returner:id,name', 'canceller:id,name',
        ]);

        $kind = $invoice->kind;
        $status = $invoice->status;
        $isDraft = $status === InvoiceStatus::Draft;
        $isMaker = $invoice->created_by === $user->id;
        $irnWindowOpen = ($invoice->ack_at ?? $invoice->issued_at)?->copy()->addHours((int) config('billing.irn_cancel_hours'))->isFuture() ?? false;
        $credited = $kind === InvoiceKind::Tax ? $drafts->creditedSoFar($invoice) : collect();
        $hasReceipts = $invoice->receipts->whereNull('reversed_at')->isNotEmpty();
        $liveChildren = $invoice->children->where('status', '!=', InvoiceStatus::Cancelled)->isNotEmpty();

        return Inertia::render('Invoices/Show', [
            'invoice' => [
                ...self::row($invoice),
                'title' => $invoice->title(),
                'kind_label' => $kind->label(),
                'billed_name' => $invoice->billed_name,
                'billed_address' => $invoice->billed_address,
                'billed_gstin' => $invoice->billed_gstin,
                'place_of_supply' => $invoice->placeOfSupply?->name,
                'inter_state' => $invoice->inter_state,
                'gst_applies' => $invoice->gst_applies,
                'sac' => $invoice->sac,
                'period_from' => $invoice->period_from?->toDateString(),
                'period_to' => $invoice->period_to?->toDateString(),
                'taxable_amount' => $invoice->taxable_amount,
                'non_taxable_amount' => $invoice->non_taxable_amount,
                'cgst_rate' => $invoice->cgst_rate, 'sgst_rate' => $invoice->sgst_rate, 'igst_rate' => $invoice->igst_rate,
                'cgst' => $invoice->cgst, 'sgst' => $invoice->sgst, 'igst' => $invoice->igst,
                'notes' => $invoice->notes,
                'irn' => $invoice->irn,
                'ack_no' => $invoice->ack_no,
                'ack_at' => $invoice->ack_at?->toIso8601String(),
                'irn_cancelled_at' => $invoice->irn_cancelled_at?->toIso8601String(),
                'has_pdf' => $invoice->pdf_path !== null,
                'created_by' => $invoice->creator->name,
                'created_at' => $invoice->created_at?->toIso8601String(),
                'issued_by' => $invoice->issuer?->name,
                'issued_at' => $invoice->issued_at?->toIso8601String(),
                'returned' => $invoice->returned_reason ? ['reason' => $invoice->returned_reason, 'by' => $invoice->returner?->name, 'at' => $invoice->returned_at?->toIso8601String()] : null,
                'cancelled' => $invoice->cancelled_at ? ['reason' => $invoice->cancel_reason, 'by' => $invoice->canceller?->name, 'at' => $invoice->cancelled_at->toIso8601String()] : null,
                'parent' => $invoice->parent ? ['id' => $invoice->parent->ulid, 'title' => $invoice->parent->title(), 'status_label' => $invoice->parent->status->label()] : null,
                'children' => $invoice->children->map(fn (Invoice $c) => [
                    'id' => $c->ulid, 'title' => $c->title(), 'total' => $c->total,
                    'status_label' => $c->status->label(), 'status_tone' => $c->status->tone(),
                    'invoice_date' => $c->invoice_date?->toDateString(),
                ])->values(),
                'lines' => $invoice->lines->map(fn (InvoiceLine $l) => [
                    'id' => $l->id,
                    'kind' => $l->kind->value,
                    'kind_label' => $l->kind->label(),
                    'description' => $l->description,
                    'period_from' => $l->period_from?->toDateString(),
                    'period_to' => $l->period_to?->toDateString(),
                    'taxable' => $l->taxable,
                    'amount' => $l->amount,
                    'creditable' => $kind === InvoiceKind::Tax ? (string) BigDecimal::of($l->amount)->minus($credited->get($l->id, '0')) : null,
                    'period_id' => $l->fee_schedule_period_id,
                    'expense_id' => $l->expense?->ulid,
                ])->values(),
                'receipts' => $invoice->receipts->map(fn (InvoiceReceipt $r) => [
                    'id' => $r->ulid,
                    'received_on' => $r->received_on->toDateString(),
                    'amount' => $r->amount,
                    'tds_amount' => $r->tds_amount,
                    'utr' => $r->utr,
                    'remark' => $r->remark,
                    'recorded_by' => $r->creator->name,
                    'reversed' => $r->reversed_at ? ['reason' => $r->reversal_reason, 'by' => $r->reverser?->name, 'at' => $r->reversed_at->toIso8601String()] : null,
                ])->values(),
                'mails' => $invoice->mails->map(fn (InvoiceMail $m) => [
                    'recipients' => $m->recipients,
                    'sent_by' => $m->sender?->name,
                    'at' => $m->created_at?->toIso8601String(),
                ])->values(),
            ],
            'history' => Activity::query()->where('subject_type', $invoice->getMorphClass())->where('subject_id', $invoice->id)
                ->with('causer')->latest('id')->limit(100)->get()
                ->map(fn (Activity $a) => ($text = self::describe($a)) === null ? null : [
                    'text' => $text,
                    'by' => $a->causer instanceof Model ? $a->causer->getAttribute('name') : null,
                    'at' => $a->created_at?->toIso8601String(),
                ])->filter()->values(),
            'can' => [
                'revise' => $isDraft && $isMaker && $kind !== InvoiceKind::CreditNote && $user->can('billing.raise'),
                'discard' => $isDraft && $isMaker && $user->can('billing.raise'),
                'issue' => $isDraft && ! $isMaker && $user->can('billing.approve'),
                'sendBack' => $isDraft && ! $isMaker && $invoice->returned_reason === null && $user->can('billing.approve'),
                'convert' => $kind === InvoiceKind::Proforma && $status === InvoiceStatus::Issued && $user->can('billing.approve'),
                'cancel' => $status === InvoiceStatus::Issued && ! $hasReceipts && ! $liveChildren && (! $kind->needsIrn() || $irnWindowOpen) && $user->can('billing.approve'),
                'creditNote' => $kind === InvoiceKind::Tax && $status === InvoiceStatus::Issued && $user->can('billing.raise'),
                'receipt' => $invoice->isCollectable() && BigDecimal::of($invoice->balance_due)->isPositive() && $user->can('billing.receipts'),
                'reverseReceipt' => $user->can('billing.receipts'),
                'resend' => ! $isDraft && $user->can('billing.raise'),
            ],
            'revise' => $isDraft && $isMaker && $kind !== InvoiceKind::CreditNote ? $this->reviseOptions($invoice) : null,
            'cancelBlockedBecause' => match (true) {
                $status !== InvoiceStatus::Issued => null,
                $hasReceipts => 'Money has been recorded against it; reverse the receipts first.',
                $liveChildren => $kind === InvoiceKind::Tax ? 'It has credit notes.' : 'A tax invoice has been made from it.',
                $kind->needsIrn() && ! $irnWindowOpen => 'Past the '.config('billing.irn_cancel_hours').'-hour cancellation window; raise a credit note instead.',
                default => null,
            },
            'today' => today()->toDateString(),
        ]);
    }

    /** One history entry in words; null for bookkeeping changes (amounts re-worked, PDF path). */
    private static function describe(Activity $activity): ?string
    {
        $new = $activity->properties['attributes'] ?? [];
        $old = $activity->properties['old'] ?? [];
        if ($activity->event === 'created') {
            return 'Drafted';
        }
        $status = InvoiceStatus::tryFrom((string) ($new['status'] ?? ''));
        if ($status !== null && ($old['status'] ?? null) !== $new['status']) {
            return match ($status) {
                InvoiceStatus::Issued => ($old['status'] ?? null) === InvoiceStatus::Draft->value ? 'Issued as '.($new['number'] ?? '') : 'Issued again (its tax invoice was cancelled)',
                InvoiceStatus::Converted => 'Converted to a tax invoice',
                InvoiceStatus::Cancelled => 'Cancelled: '.($new['cancel_reason'] ?? ''),
                InvoiceStatus::Draft => 'Back to draft',
            };
        }
        if (! empty($new['returned_reason']) && ($old['returned_reason'] ?? null) !== $new['returned_reason']) {
            return 'Sent back: '.$new['returned_reason'];
        }
        if (array_key_exists('returned_reason', $new) && $new['returned_reason'] === null && ! empty($old['returned_reason'])) {
            return 'Changed by its maker';
        }
        if (! empty($new['irn']) && ($old['irn'] ?? null) !== $new['irn']) {
            return 'IRN registered';
        }

        return null;
    }

    /**
     * What a draft's maker can change it to: unbilled periods and expenses plus those on it now.
     *
     * @return array<string, mixed>
     */
    private function reviseOptions(Invoice $invoice): array
    {
        $dealId = $invoice->transaction_id;
        $free = fn (Builder $q) => $q->whereNull('invoice_id')->orWhere('invoice_id', $invoice->id);

        return [
            'periods' => FeeSchedulePeriod::query()->whereHas('feeLine', fn (Builder $q) => $q->where('transaction_id', $dealId))->where($free)
                ->with('feeLine:id,kind')->orderBy('from_date')->get()
                ->map(fn (FeeSchedulePeriod $p) => ['id' => $p->id, 'fee' => $p->feeLine->kind->label(), 'from' => $p->from_date->toDateString(), 'to' => $p->to_date->toDateString(), 'bill_date' => $p->bill_date->toDateString(), 'amount' => $p->amount])->values(),
            'expenses' => DealExpense::query()->where('transaction_id', $dealId)->whereNull('removed_at')->where($free)->orderBy('incurred_on')->get()
                ->map(fn (DealExpense $e) => ['id' => $e->ulid, 'description' => $e->description, 'incurred_on' => $e->incurred_on?->toDateString(), 'amount' => $e->amount])->values(),
            'initial' => [
                'periods' => $invoice->lines->pluck('fee_schedule_period_id')->filter()->values(),
                'expenses' => $invoice->lines->filter(fn (InvoiceLine $l) => $l->deal_expense_id !== null)->map(fn (InvoiceLine $l) => $l->expense?->ulid)->filter()->values(),
                'others' => $invoice->lines->where('kind', InvoiceLineKind::Other)->map(fn (InvoiceLine $l) => ['description' => $l->description, 'amount' => $l->amount])->values(),
                'notes' => $invoice->notes ?? '',
            ],
        ];
    }

    /** The PDF: the stored one once issued, a preview (marked DRAFT) before that. */
    public function pdf(Invoice $invoice, InvoiceRenderer $renderer): HttpResponse
    {
        $this->authorize('billing.view');
        $name = str_replace('/', '-', $invoice->number ?? 'draft-'.$invoice->ulid).'.pdf';

        if ($invoice->pdf_path === null) {
            return Pdf::loadHTML($renderer->html($invoice))->setPaper('a4')->stream($name);
        }
        abort_unless(Storage::disk('local')->exists($invoice->pdf_path), 404);

        return Storage::disk('local')->download($invoice->pdf_path, $name);
    }

    public function update(DraftInvoiceRequest $request, Invoice $invoice, DraftInvoice $drafts): RedirectResponse
    {
        $drafts->revise($invoice, array_map('intval', $request->validated('periods', [])), $request->validated('expenses', []), $request->validated('others', []), $request->validated('notes'), $request->user());

        return back()->with('success', 'Draft saved. It\'s waiting to be issued.');
    }

    public function destroy(Request $request, Invoice $invoice, DraftInvoice $drafts): RedirectResponse
    {
        $this->authorize('billing.raise');
        $deal = $invoice->transaction()->value('ulid');
        $drafts->discard($invoice, $request->user());

        return redirect()->route('deals.show', ['transaction' => $deal, 'tab' => 'invoices'])->with('success', 'Draft discarded.');
    }

    public function issue(Request $request, Invoice $invoice, IssueInvoice $issue): RedirectResponse
    {
        $this->authorize('billing.approve');
        ['invoice' => $issued, 'emailed' => $emailed] = $issue->issue($invoice, $request->user());

        return back()->with('success', "{$issued->title()} issued.".($emailed ? ' Emailed to the client.' : ' The deal has no billing contact with an email, so it wasn\'t emailed.'));
    }

    public function sendBack(ReasonRequest $request, Invoice $invoice, IssueInvoice $issue): RedirectResponse
    {
        $issue->sendBack($invoice, $request->validated('reason'), $request->user());

        return back()->with('success', 'Sent back to its maker.');
    }

    public function convert(Request $request, Invoice $invoice, IssueInvoice $issue): RedirectResponse
    {
        $this->authorize('billing.approve');
        ['invoice' => $tax, 'emailed' => $emailed] = $issue->convert($invoice, $request->user());

        return redirect()->route('invoices.show', $tax)->with('success', "{$tax->title()} issued.".($emailed ? ' Emailed to the client.' : ''));
    }

    public function cancel(ReasonRequest $request, Invoice $invoice, IssueInvoice $issue): RedirectResponse
    {
        $issue->cancel($invoice, $request->validated('reason'), $request->user());

        return back()->with('success', "{$invoice->title()} cancelled.");
    }

    public function resend(Request $request, Invoice $invoice, IssueInvoice $issue): RedirectResponse
    {
        $this->authorize('billing.raise');
        $sent = $issue->resend($invoice, $request->user());

        return back()->with('success', $sent === 1 ? 'Emailed to 1 person.' : "Emailed to {$sent} people.");
    }

    public function creditNote(CreditNoteRequest $request, Invoice $invoice, DraftInvoice $drafts): RedirectResponse
    {
        $note = $drafts->creditNote($invoice, $request->validated('amounts'), $request->validated('reason'), $request->user());

        return redirect()->route('invoices.show', $note)->with('success', 'Credit note drafted. Someone else issues it.');
    }

    public function receipt(ReceiptRequest $request, Invoice $invoice, RecordReceipt $receipts): RedirectResponse
    {
        $receipts->record($invoice, $request->validated(), $request->user());

        return back()->with('success', 'Receipt recorded.');
    }

    public function reverseReceipt(ReasonRequest $request, InvoiceReceipt $receipt, RecordReceipt $receipts): RedirectResponse
    {
        $receipts->reverse($receipt, $request->validated('reason'), $request->user());

        return back()->with('success', 'Receipt reversed.');
    }

    /**
     * @return Builder<Invoice>
     */
    public static function query(): Builder
    {
        return Invoice::query()->with(['transaction:id,ulid,el_number,company_id', 'transaction.company:id,name', 'creator:id,name']);
    }

    private static function tabFrom(Request $request): string
    {
        $tab = $request->input('filter.tab');

        return in_array($tab, self::TABS, true) ? $tab : 'due';
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    private static function tab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'drafts' => $query->where('status', InvoiceStatus::Draft),
            'due' => $query->due(),
            'cancelled' => $query->where('status', InvoiceStatus::Cancelled),
            default => $query->where('kind', InvoiceKind::from($tab))->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Converted]),
        };
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private static function search(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }
        $like = '%'.$term.'%';
        $query->where(fn (Builder $q) => $q
            ->where('number', 'like', $like)
            ->orWhere('billed_name', 'like', $like)
            ->orWhere('billed_gstin', 'like', $like)
            ->orWhereHas('transaction', fn (Builder $t) => $t->where('el_number', 'like', $like)
                ->orWhereHas('company', fn (Builder $c) => $c->where('name', 'like', $like))));
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(Invoice $invoice): array
    {
        return [
            'id' => $invoice->ulid,
            'kind' => $invoice->kind->value,
            'kind_label' => $invoice->kind->shortLabel(),
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'status_tone' => $invoice->status->tone(),
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'deal' => ['id' => $invoice->transaction->ulid, 'el_number' => $invoice->transaction->el_number, 'company' => $invoice->transaction->company->name],
            'total' => $invoice->total,
            'balance_due' => $invoice->isCollectable() ? $invoice->balance_due : null,
            'overdue' => $invoice->isCollectable() && $invoice->isOverdue(),
            'returned' => $invoice->returned_reason !== null,
            'maker' => $invoice->creator?->name,
        ];
    }
}
