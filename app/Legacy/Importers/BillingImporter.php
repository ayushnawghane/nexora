<?php

namespace App\Legacy\Importers;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceLineKind;
use App\Enums\InvoiceStatus;
use App\Legacy\Importer;
use App\Legacy\Models\LegacyRecord;
use App\Models\DealExpense;
use App\Models\DocumentFile;
use App\Models\FeeSchedulePeriod;
use App\Models\Invoice;
use App\Models\InvoiceReceipt;
use App\Models\NumberSequence;
use App\Models\State;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Billing\InvoiceNumbers;
use App\Support\FinancialYear;
use App\Support\IndianIdentifiers;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Invoices of the imported DT deals from Stack's `tblpushbillingdata` (its own invoice table), with
 * their out-of-pocket expenses, receipts, IRNs and the fee periods they billed. Needs the
 * transactions area first.
 *
 * - Kinds: Proforma → proforma, Tax → tax invoice, Credit → credit note, Debit → reimbursement bill
 *   (Stack's "debit notes" bill out-of-pocket expenses, without GST). Inactive rows (replaced or
 *   failed in Stack) are left out.
 * - Status: cancelled when Stack cancelled it (status 13 / is_cancelled); a proforma with a live tax
 *   invoice is converted; otherwise issued.
 * - Lines come from Stack's amounts per fee type (acceptance, service, other, OPE). Where those
 *   don't add up to Stack's sub-total, one line carries the sub-total as billed. Totals are kept
 *   exactly as Stack billed them.
 * - Payments (`payment_update`, one running row per bill) become one receipt each, on the proforma
 *   or reimbursement bill (a payment Stack put on a tax invoice moves to its proforma).
 * - A number used twice for the same kind keeps it on the first; the later one gets "-{Stack id}".
 * - Number sequences continue after the highest number Stack used in each financial year.
 */
class BillingImporter extends Importer
{
    private const PRODUCT = 4;

    /** The tblpushbillingdata columns the import reads. */
    private const COLUMNS = [
        'id', 'con_id', 'invoice_type', 'invoice_no', 'proforma_id', 'status', 'is_cancelled', 'cancelled_date', 'cancelled_by',
        'cancelled_reason', 'created_date', 'user_id', 'billing_name', 'company_name', 'billing_address', 'address', 'state',
        'gst_no', 'is_gst_apply', 'bill_start_date', 'bill_end_date', 'accep_manually', 'service_manually', 'brk_amnt',
        'other_amnt', 'other_desc', 'ope_amount', 'sub_total', 'cgst', 'sgst', 'igst', 'grand_total',
    ];

    private const KINDS = ['Proforma' => InvoiceKind::Proforma, 'Debit' => InvoiceKind::Reimbursement, 'Tax' => InvoiceKind::Tax, 'Credit' => InvoiceKind::CreditNote];

    private int $importUser;

    /** @var array<string, int> "{kind}|{number}" => Nexora invoice id, to catch repeated numbers */
    private array $numbers = [];

    /** @var array<string, int> "{Stack type}|{invoice_no}" => Nexora invoice id (tax → proforma, credit → tax) */
    private array $byStackNumber = [];

    /** @var array<string, true> proforma numbers a live (not cancelled) Stack tax invoice was made from */
    private array $converted = [];

    /** @var array<int, int> Stack bill id => Nexora deal id */
    private array $dealOf = [];

    /** @var array<int, string> Stack proforma id => SAC */
    private array $sac = [];

    /** @var array<int, LegacyRecord> live e-invoice rows by Stack bill id */
    private array $irns = [];

    /** @var array<string, int> state name (lower case) => id */
    private array $states = [];

    /** @var array<int, LegacyRecord> upload_file rows by id */
    private array $uploads = [];

    public function area(): string
    {
        return 'billing';
    }

    public function description(): string
    {
        return 'DT deals\' invoices (proforma, tax, credit notes, reimbursement bills), expenses, receipts and IRNs';
    }

    protected function import(): void
    {
        $user = $this->importUser();
        if ($user === null) {
            return;
        }
        $this->importUser = $user;

        $legacyDeals = LegacyRecord::from('transaction')->where('product_id', self::PRODUCT)->where('is_deleted', 0)->pluck('id')->all();
        $deals = Transaction::query()->whereIn('legacy_id', $legacyDeals)->get(['id', 'ulid', 'legacy_id', 'created_at'])->keyBy('legacy_id');
        $this->states = State::query()->get(['id', 'name'])->mapWithKeys(fn (State $s) => [Str::lower($s->name) => $s->id])->all();

        foreach (LegacyRecord::from('tblpushbilling_conid_mapper')->whereIn('con_id', $deals->keys()->all())->orderBy('is_active')->get() as $map) {
            $deal = $deals->get($map->ref('con_id'));
            foreach (['tblpushbill_id', 'taxinvoice_id', 'credit_id', 'debit_id'] as $column) {
                if ($deal && $map->ref($column)) {
                    $this->dealOf[(int) $map->ref($column)] = $deal->id;
                }
            }
        }

        // Only DT deals' bills (linked through the mapper, or by con_id), and only the columns used.
        $rows = LegacyRecord::from('tblpushbillingdata')->where('is_active', 1)
            ->where(fn ($q) => $q->whereIn('id', array_keys($this->dealOf))->orWhereIn('con_id', $deals->keys()->all()))
            ->orderBy('id')->get(self::COLUMNS);
        $billIds = $rows->pluck('id')->all();
        foreach (array_chunk($billIds, 5000) as $chunk) {
            foreach (LegacyRecord::from('tblpushbilling_hsn_mapper')->whereIn('tblpushbill_id', $chunk)->orderBy('id')->get() as $hsn) {
                $this->sac[(int) $hsn->ref('tblpushbill_id')] = (string) $hsn->text('hsn');
            }
            foreach (LegacyRecord::from('tbl_einvoice_details')->whereIn('bill_id', $chunk)->where('is_active', 1)->orderBy('id')->get(['id', 'bill_id', 'irn', 'ack_no', 'ack_date', 'is_cancelled', 'created_date']) as $irn) {
                if ($irn->text('irn')) {
                    $this->irns[(int) $irn->ref('bill_id')] = $irn;
                }
            }
        }

        // Decided up front, so a re-run saves each proforma once with its final status.
        foreach ($rows as $r) {
            if (Str::lower((string) $r->text('invoice_type')) === 'tax' && (string) $r->getAttribute('status') !== '13' && (int) $r->getAttribute('is_cancelled') !== 1 && $r->text('proforma_id')) {
                $this->converted[(string) $r->text('proforma_id')] = true;
            }
        }

        foreach (self::KINDS as $type => $kind) {
            foreach ($rows->filter(fn (LegacyRecord $r) => Str::lower((string) $r->text('invoice_type')) === Str::lower($type)) as $row) {
                $this->invoice($row, $type, $kind, $deals);
            }
        }
        $rows->filter(fn (LegacyRecord $r) => ! array_key_exists(Str::ucfirst(Str::lower((string) $r->text('invoice_type'))), self::KINDS))
            ->each(fn (LegacyRecord $r) => $this->report->reject('invoices', $r->id, "Stack type \"{$r->text('invoice_type')}\" isn't an invoice kind."));

        $this->periods();
        $this->expenses($deals);
        $this->receipts();
        $this->balances();
        $this->sequences();
        $this->reconcile($rows);
    }

    /**
     * @param  Collection<int|string, Transaction>  $deals
     */
    private function invoice(LegacyRecord $row, string $type, InvoiceKind $kind, Collection $deals): void
    {
        $this->report->read('invoices');
        $dealId = $this->dealOf[(int) $row->id] ?? $deals->get($row->ref('con_id'))?->id;
        if ($dealId === null) {
            return; // another product's bill (only DT deals are imported); not reported, it's most of the table
        }

        $number = $row->text('invoice_no');
        if ($number === null || $number === '0') {
            $this->report->reject('invoices', $row->id, "{$type} without a number.");

            return;
        }
        $key = "{$kind->value}|{$number}";
        if (isset($this->numbers[$key]) && $this->numbers[$key] !== $this->existingId((int) $row->id)) {
            $this->report->warn('invoices', $row->id, "{$type} number {$number} is used twice in Stack; this one is imported as {$number}-{$row->id}.");
            $number = "{$number}-{$row->id}";
        }

        $parentId = match ($kind) {
            InvoiceKind::Tax => $this->byStackNumber['Proforma|'.$row->text('proforma_id')] ?? null,
            InvoiceKind::CreditNote => $this->byStackNumber['Tax|'.$row->text('proforma_id')] ?? null,
            default => null,
        };
        if (in_array($kind, [InvoiceKind::Tax, InvoiceKind::CreditNote], true) && $parentId === null) {
            $this->report->warn('invoices', $row->id, "{$type} {$number}: its ".($kind === InvoiceKind::Tax ? 'proforma' : 'tax invoice')." \"{$row->text('proforma_id')}\" isn't among the imported invoices.");
        }

        $cancelled = (string) $row->getAttribute('status') === '13' || (int) $row->getAttribute('is_cancelled') === 1;
        $date = substr((string) ($row->date('created_date') ?? ''), 0, 10) ?: null;
        $user = $this->idFor(User::class, $row->ref('user_id')) ?? $this->importUser;
        $money = fn (string $column) => (string) BigDecimal::of((string) ($row->getAttribute($column) ?? '0') ?: '0')->toScale(2, RoundingMode::HalfUp);
        $reimbursement = $kind === InvoiceKind::Reimbursement;
        [$taxable, $other, $cgst, $sgst, $igst, $total] = $reimbursement
            ? ['0.00', $money('grand_total'), '0.00', '0.00', '0.00', $money('grand_total')]
            : [$money('sub_total'), $money('ope_amount'), $money('cgst'), $money('sgst'), $money('igst'), $money('grand_total')];
        $rate = fn (string $tax) => BigDecimal::of($taxable)->isPositive() ? (string) BigDecimal::of($tax)->multipliedBy(100)->dividedBy($taxable, 2, RoundingMode::HalfUp) : '0';
        $gstin = Str::upper((string) $row->text('gst_no'));
        $gstin = IndianIdentifiers::isGstin($gstin) ? $gstin : null;
        $irn = $this->irns[(int) $row->id] ?? null;
        $proformaId = $kind === InvoiceKind::Proforma ? (int) $row->id : null;

        /** @var Invoice $invoice */
        $invoice = $this->upsert(Invoice::class, (int) $row->id, [
            'ulid' => $this->existingUlid((int) $row->id) ?? (string) Str::ulid(),
            'transaction_id' => $dealId,
            'kind' => $kind,
            'status' => match (true) {
                $cancelled => InvoiceStatus::Cancelled,
                $kind === InvoiceKind::Proforma && isset($this->converted[(string) $row->text('invoice_no')]) => InvoiceStatus::Converted,
                default => InvoiceStatus::Issued,
            },
            'parent_id' => $parentId,
            'number' => $number,
            'financial_year' => $this->financialYear($number, $date),
            'serial' => $this->serial($number),
            'invoice_date' => $date,
            'billed_name' => mb_substr((string) ($row->text('billing_name') ?? $row->text('company_name') ?? ''), 0, 255) ?: null,
            'billed_address' => $row->text('billing_address') ?? $row->text('address'),
            'billed_gstin' => $gstin,
            'place_of_supply_state_id' => ($gstin ? $this->stateByCode(substr($gstin, 0, 2)) : null) ?? $this->states[Str::lower((string) $row->text('state'))] ?? null,
            'inter_state' => BigDecimal::of($igst)->isPositive(),
            'sac' => $reimbursement ? null : ($this->sac[$proformaId ?? 0] ?? (string) config('billing.sac')),
            'gst_applies' => ! $reimbursement && (int) $row->getAttribute('is_gst_apply') !== 1,
            'period_from' => $row->date('bill_start_date'),
            'period_to' => $row->date('bill_end_date'),
            'taxable_amount' => $taxable,
            'non_taxable_amount' => $other,
            'cgst_rate' => $rate($cgst),
            'sgst_rate' => $rate($sgst),
            'igst_rate' => $rate($igst),
            'cgst' => $cgst,
            'sgst' => $sgst,
            'igst' => $igst,
            'total' => $total,
            'irn' => $irn ? mb_substr((string) $irn->text('irn'), 0, 64) : null,
            'ack_no' => $irn ? mb_substr((string) $irn->text('ack_no'), 0, 32) : null,
            'ack_at' => $irn?->date('ack_date'),
            'irn_cancelled_at' => $irn && (int) $irn->getAttribute('is_cancelled') === 1 ? ($irn->date('created_date') ?? $date) : null,
            'created_by' => $user,
            'issued_by' => $user,
            'issued_at' => $row->date('created_date'),
            'cancel_reason' => $cancelled ? ($row->text('cancelled_reason') ?? 'Cancelled in Stack') : null,
            'cancelled_by' => $cancelled ? ($this->idFor(User::class, $row->ref('cancelled_by')) ?? $user) : null,
            'cancelled_at' => $cancelled ? ($row->date('cancelled_date') ?? $row->date('created_date')) : null,
            'created_at' => $row->date('created_date'),
        ], 'invoices');

        $this->numbers[$key] = $invoice->id;
        $this->byStackNumber["{$type}|{$row->text('invoice_no')}"] ??= $invoice->id;
        $this->lines($invoice, $row, $kind);

        if (! $this->checkTotal($invoice)) {
            $this->report->warn('invoices', $row->id, "{$number}: Stack's total ({$total}) isn't its amounts plus tax; kept as Stack billed it.");
        }
    }

    /** Lines from Stack's amounts per fee type. Re-runs replace them (they hold nothing else). */
    private function lines(Invoice $invoice, LegacyRecord $row, InvoiceKind $kind): void
    {
        $money = fn (string $column) => BigDecimal::of((string) ($row->getAttribute($column) ?? '0') ?: '0')->toScale(2, RoundingMode::HalfUp);
        $from = $row->date('bill_start_date');
        $to = $row->date('bill_end_date');
        $lines = [];

        if ($kind === InvoiceKind::Reimbursement) {
            $lines[] = ['kind' => InvoiceLineKind::Expense, 'description' => 'Out-of-pocket expenses', 'taxable' => false, 'amount' => (string) $money('grand_total')];
        } else {
            $acceptance = $money('accep_manually');
            $service = $money('service_manually');
            $other = $money('other_amnt');
            $subTotal = $money('sub_total');
            if (! $acceptance->plus($service)->plus($other)->isEqualTo($subTotal)) {
                $service = $money('brk_amnt');
            }
            if ($acceptance->plus($service)->plus($other)->isEqualTo($subTotal)) {
                foreach ([[InvoiceLineKind::Acceptance, $acceptance, null], [InvoiceLineKind::Service, $service, null], [InvoiceLineKind::Other, $other, $row->text('other_desc')]] as [$lineKind, $amount, $label]) {
                    if ($amount->isPositive()) {
                        $lines[] = ['kind' => $lineKind, 'description' => mb_substr($label ?: $lineKind->label(), 0, 255), 'taxable' => true, 'amount' => (string) $amount];
                    }
                }
            } elseif ($subTotal->isPositive()) {
                $lines[] = ['kind' => InvoiceLineKind::Other, 'description' => 'Trusteeship fees (as billed in Stack)', 'taxable' => true, 'amount' => (string) $subTotal];
            }
            $ope = $money('ope_amount');
            if ($ope->isPositive()) {
                $lines[] = ['kind' => InvoiceLineKind::Expense, 'description' => 'Out-of-pocket expenses', 'taxable' => false, 'amount' => (string) $ope];
            }
        }

        $current = $invoice->lines()->get(['kind', 'description', 'taxable', 'amount'])
            ->map(fn ($l) => [$l->kind->value, $l->description, $l->taxable, $l->amount])->all();
        $wanted = array_map(fn (array $l) => [$l['kind']->value, $l['description'], $l['taxable'], $l['amount']], $lines);
        if ($current === $wanted) {
            return;
        }
        $invoice->lines()->delete();
        foreach ($lines as $i => $line) {
            $invoice->lines()->create([...$line, 'position' => $i + 1, 'period_from' => $from, 'period_to' => $to]);
        }
    }

    /** Marks the fee periods Stack billed on each live proforma as billed by it. */
    private function periods(): void
    {
        $proformas = Invoice::query()->where('kind', InvoiceKind::Proforma)->whereNotNull('legacy_id')->where('status', '!=', InvoiceStatus::Cancelled)->pluck('id', 'legacy_id');
        $periods = FeeSchedulePeriod::query()->whereNotNull('legacy_id')->pluck('id', 'legacy_id');
        if ($periods->isEmpty()) {
            return;
        }
        foreach ($periods->keys()->chunk(5000) as $chunk) {
            foreach (LegacyRecord::from('master_cl_schedules')->whereIn('id', $chunk->all())->where('proforma_id', '>', 0)->get(['id', 'proforma_id']) as $row) {
                $this->report->read('billed periods linked');
                $invoiceId = $proformas[$row->ref('proforma_id')] ?? null;
                $period = FeeSchedulePeriod::query()->find($periods[$row->id]);
                if ($period === null) {
                    continue;
                }
                $period->forceFill(['invoice_id' => $invoiceId]);
                $this->save($period, 'billed periods linked');
            }
        }
    }

    /**
     * Stack's out-of-pocket expenses, billed on the proforma or debit note that took them.
     *
     * @param  Collection<int|string, Transaction>  $deals
     */
    private function expenses(Collection $deals): void
    {
        $rows = LegacyRecord::from('ope_billing_details')->whereIn('con_id', $deals->keys()->all())->where('is_active', 1)->where('is_deleted', 0)->orderBy('id')->get();
        $this->uploads = [];
        foreach ($rows->pluck('upload_id')->filter(fn ($id) => (int) $id > 0)->unique()->chunk(5000) as $chunk) {
            foreach (LegacyRecord::from('upload_file')->whereIn('id', $chunk->all())->get(['id', 'name', 'path', 'created_by', 'created_at']) as $upload) {
                $this->uploads[(int) $upload->id] = $upload;
            }
        }
        $live = Invoice::query()->whereNotNull('legacy_id')->where('status', '!=', InvoiceStatus::Cancelled)
            ->whereIn('kind', [InvoiceKind::Proforma, InvoiceKind::Reimbursement])->pluck('id', 'legacy_id');

        foreach ($rows as $row) {
            $this->report->read('expenses');
            /** @var Transaction $deal */
            $deal = $deals->get($row->ref('con_id'));
            $amount = BigDecimal::of((string) ($row->getAttribute('pocket_amount') ?? '0') ?: '0')->toScale(2, RoundingMode::HalfUp);
            if (! $amount->isPositive()) {
                $this->report->reject('expenses', $row->id, 'No amount.');

                continue;
            }
            /** @var DealExpense $expense */
            $expense = $this->upsert(DealExpense::class, (int) $row->id, [
                'ulid' => DealExpense::query()->where('legacy_id', $row->id)->value('ulid') ?? (string) Str::ulid(),
                'transaction_id' => $deal->id,
                'incurred_on' => substr((string) ($row->date('created_at') ?? ''), 0, 10) ?: null,
                'description' => mb_substr($row->text('remark') ?? 'Out-of-pocket expense', 0, 255),
                'amount' => (string) $amount,
                'invoice_id' => $live[$row->ref('bill_id')] ?? null,
                'created_by' => $this->idFor(User::class, $row->ref('created_by')) ?? $this->importUser,
                'created_at' => $row->date('created_at'),
            ], 'expenses');
            if ($row->ref('upload_id') && ! $this->report->dryRun) {
                $this->file($expense, $deal, (int) $row->ref('upload_id'));
            }
        }
    }

    /** One receipt per Stack payment row, on the proforma or reimbursement bill it settles. */
    private function receipts(): void
    {
        $invoices = Invoice::query()->whereNotNull('legacy_id')->get(['id', 'legacy_id', 'kind', 'parent_id', 'invoice_date'])->keyBy('legacy_id');
        if ($invoices->isEmpty()) {
            return;
        }
        foreach ($invoices->keys()->chunk(5000) as $chunk) {
            foreach (LegacyRecord::from('payment_update')->whereIn('tbl_pushbilling_id', $chunk->all())->where('is_active', 1)->orderBy('id')->get() as $row) {
                $this->report->read('receipts');
                /** @var Invoice $invoice */
                $invoice = $invoices[$row->ref('tbl_pushbilling_id')];
                $target = match ($invoice->kind) {
                    InvoiceKind::Proforma, InvoiceKind::Reimbursement => $invoice->id,
                    InvoiceKind::Tax => $invoice->parent_id,
                    InvoiceKind::CreditNote => null,
                };
                if ($target === null) {
                    $this->report->reject('receipts', $row->id, "Payment on {$invoice->kind->label()} with no proforma to move it to.");

                    continue;
                }
                $amount = BigDecimal::of((string) ($row->getAttribute('received_amount') ?? '0') ?: '0')->toScale(2, RoundingMode::HalfUp);
                $tds = BigDecimal::of((string) ($row->getAttribute('tds_amount') ?? '0') ?: '0')->toScale(2, RoundingMode::HalfUp);
                if ($amount->plus($tds)->isLessThanOrEqualTo(0)) {
                    $this->report->reject('receipts', $row->id, 'Nothing received.');

                    continue;
                }
                // Stack sometimes left the date received empty: fall back to the TDS date, then when
                // the payment was entered, then the invoice date.
                $receivedOn = $row->date('recieved_amt_date');
                if ($receivedOn === null) {
                    $receivedOn = $row->date('tds_date') ?? $row->date('created_at') ?? $invoices->firstWhere('id', $target)?->invoice_date?->toDateString();
                    $this->report->warn('receipts', $row->id, 'No date received in Stack; dated '.($receivedOn ?? 'unknown').'.');
                }
                if ($receivedOn === null) {
                    $this->report->reject('receipts', $row->id, 'No date at all.');

                    continue;
                }
                $utr = $row->text('utr_no');
                $cleanUtr = $utr !== null && strlen($utr) <= 40 && preg_match('/^[A-Za-z0-9\-\/]+$/', $utr) ? $utr : null;
                $this->upsert(InvoiceReceipt::class, (int) $row->id, [
                    'ulid' => InvoiceReceipt::query()->where('legacy_id', $row->id)->value('ulid') ?? (string) Str::ulid(),
                    'invoice_id' => $target,
                    'received_on' => substr($receivedOn, 0, 10),
                    'amount' => (string) $amount,
                    'tds_amount' => (string) $tds,
                    'utr' => $cleanUtr,
                    'remark' => $utr !== null && $cleanUtr === null ? mb_substr("UTR / reference in Stack: {$utr}", 0, 2000) : null,
                    'created_by' => $this->idFor(User::class, $row->ref('created_by')) ?? $this->importUser,
                    'created_at' => $row->date('created_at'),
                ], 'receipts');
            }
        }
    }

    private function balances(): void
    {
        Invoice::query()->whereNotNull('legacy_id')->whereIn('kind', [InvoiceKind::Proforma, InvoiceKind::Reimbursement])
            ->chunkById(500, fn (Collection $invoices) => $invoices->each(fn (Invoice $i) => $i->recalculateBalance()));
        $overpaid = Invoice::query()->whereNotNull('legacy_id')->whereIn('kind', [InvoiceKind::Proforma, InvoiceKind::Reimbursement])
            ->where('status', '!=', InvoiceStatus::Cancelled)->whereHas('receipts')->where('balance_due', 0)->count();
        $this->report->total('Imported proformas and reimbursement bills fully settled', (string) $overpaid);
    }

    /** Each kind's sequence for each financial year continues after Stack's highest number. */
    private function sequences(): void
    {
        $highest = [];
        foreach (Invoice::query()->whereNotNull('legacy_id')->whereNotNull('serial')->whereNotNull('financial_year')->get(['kind', 'financial_year', 'serial']) as $i) {
            $key = InvoiceNumbers::key($i->kind, (string) $i->financial_year);
            $highest[$key] = max($highest[$key] ?? 0, (int) $i->serial);
        }
        foreach ($highest as $key => $serial) {
            $sequence = NumberSequence::query()->firstOrNew(['key' => $key]);
            if ((int) $sequence->last_value < $serial) {
                $sequence->last_value = $serial;
                $sequence->save();
            }
            $this->report->total("Sequence {$key} continues after", (string) $sequence->last_value);
        }
    }

    /**
     * @param  Collection<int, LegacyRecord>  $rows
     */
    private function reconcile(Collection $rows): void
    {
        $imported = Invoice::query()->whereNotNull('legacy_id')->where('status', '!=', InvoiceStatus::Cancelled)->get(['legacy_id', 'kind', 'total']);
        $ids = $imported->pluck('legacy_id')->flip();
        foreach (self::KINDS as $type => $kind) {
            $stack = $rows->filter(fn (LegacyRecord $r) => isset($ids[(int) $r->id]) && Str::lower((string) $r->text('invoice_type')) === Str::lower($type))
                ->reduce(fn (BigDecimal $s, LegacyRecord $r) => $s->plus((string) ($r->getAttribute('grand_total') ?? '0') ?: '0'), BigDecimal::zero());
            $nexora = $imported->where('kind', $kind)->reduce(fn (BigDecimal $s, Invoice $i) => $s->plus($i->total), BigDecimal::zero());
            $this->report->total("{$kind->label()} totals, not cancelled: Stack / Nexora (₹)", $stack->toScale(2, RoundingMode::HalfUp).' / '.$nexora->toScale(2, RoundingMode::HalfUp));
        }
    }

    private function checkTotal(Invoice $invoice): bool
    {
        return BigDecimal::of($invoice->taxable_amount)->plus($invoice->non_taxable_amount)->plus($invoice->cgst)->plus($invoice->sgst)->plus($invoice->igst)
            ->isEqualTo($invoice->total);
    }

    /** "BTL/2526/TAX012", "BTL/25-26/INV003" → "2526"; otherwise from the date. */
    private function financialYear(string $number, ?string $date): ?string
    {
        if (preg_match('~/(\d{2})-?(\d{2})/~', $number, $m)) {
            return $m[1].$m[2];
        }

        return $date ? FinancialYear::compact(new DateTimeImmutable($date)) : null;
    }

    /** The running number in Nexora's own format (BTL/2526/INV012 → 12); null for older formats. */
    private function serial(string $number): ?int
    {
        return preg_match('~^BTL/\d{2}-?\d{2}/(?:INV|TAX|CN|DN)(\d+)$~', $number, $m) ? (int) $m[1] : null;
    }

    private function stateByCode(string $code): ?int
    {
        static $byCode = null;
        $byCode ??= State::query()->pluck('id', 'gst_code')->all();

        return $byCode[$code] ?? null;
    }

    private function existingId(int $legacyId): ?int
    {
        return $this->idFor(Invoice::class, $legacyId);
    }

    private function existingUlid(int $legacyId): ?string
    {
        return Invoice::query()->where('legacy_id', $legacyId)->value('ulid');
    }

    private function file(DealExpense $expense, Transaction $deal, int $uploadId): void
    {
        $entity = 'expense files';
        $this->report->read($entity);
        $upload = $this->uploads[$uploadId] ?? null;
        if ($upload === null) {
            $this->report->reject($entity, $uploadId, "Upload #{$uploadId} isn't in Stack's file list.");

            return;
        }
        $key = ['attachable_type' => DealExpense::class, 'attachable_id' => $expense->id, 'legacy_id' => $uploadId];
        $file = DocumentFile::query()->where($key)->first() ?? (new DocumentFile)->forceFill($key);
        $file->forceFill([
            'original_name' => mb_substr($upload->text('name') ?? basename((string) $upload->text('path')) ?: 'file', 0, 255),
            'uploaded_by' => $this->idFor(User::class, $upload->ref('created_by')) ?? $this->importUser,
            'created_at' => $file->created_at ?? $upload->date('created_at') ?? $deal->created_at,
        ]);
        $this->save($file, $entity);

        $root = config('legacy.uploads_path');
        if ($file->isAvailable() || ! $root) {
            return;
        }
        $relative = ltrim(str_replace('\\', '/', (string) $upload->text('path')), '/');
        $source = rtrim((string) $root, '/\\').DIRECTORY_SEPARATOR.$relative;
        if ($relative === '' || str_contains($relative, '..') || ! is_file($source)) {
            $this->report->warn("{$entity} (copy)", $uploadId, "Not found in the uploads copy: {$relative}");

            return;
        }
        $extension = Str::lower(pathinfo($relative, PATHINFO_EXTENSION));
        $path = "deals/{$deal->ulid}/expenses/stack-{$uploadId}".($extension !== '' ? ".{$extension}" : '');
        Storage::disk('local')->put($path, (string) file_get_contents($source));
        $file->forceFill(['path' => $path, 'size' => filesize($source) ?: null, 'mime' => mime_content_type($source) ?: null])->save();
        $this->report->saved("{$entity} (copy)", 'created');
    }

    private function save(Model $model, string $entity): void
    {
        $outcome = ! $model->exists ? 'created' : ($model->isDirty() ? 'updated' : 'unchanged');
        if ($outcome !== 'unchanged') {
            $model->save();
        }
        $this->report->saved($entity, $outcome);
    }
}
