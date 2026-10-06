<?php

namespace App\Models;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A billing document of one deal. Drafts have no number and can change; issued documents never
 * change (God Mode aside). Amounts are decimal strings; do maths on them with brick/math.
 *
 * @property int $id
 * @property string $ulid
 * @property int $transaction_id
 * @property InvoiceKind $kind
 * @property InvoiceStatus $status
 * @property int|null $parent_id
 * @property string|null $number
 * @property string|null $financial_year
 * @property int|null $serial
 * @property Carbon|null $invoice_date
 * @property string|null $billed_name
 * @property string|null $billed_address
 * @property string|null $billed_gstin
 * @property int|null $place_of_supply_state_id
 * @property bool|null $inter_state
 * @property string|null $sac
 * @property bool $gst_applies
 * @property Carbon|null $period_from
 * @property Carbon|null $period_to
 * @property string $taxable_amount
 * @property string $non_taxable_amount
 * @property string $cgst_rate
 * @property string $sgst_rate
 * @property string $igst_rate
 * @property string $cgst
 * @property string $sgst
 * @property string $igst
 * @property string $total
 * @property string $balance_due
 * @property string|null $notes
 * @property string|null $irn
 * @property string|null $ack_no
 * @property Carbon|null $ack_at
 * @property string|null $signed_qr
 * @property Carbon|null $irn_cancelled_at
 * @property string|null $pdf_path
 * @property int $created_by
 * @property string|null $returned_reason
 * @property int|null $returned_by
 * @property Carbon|null $returned_at
 * @property int|null $issued_by
 * @property Carbon|null $issued_at
 * @property string|null $cancel_reason
 * @property int|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property int|null $legacy_id
 */
class Invoice extends Model
{
    use HasUlids;
    use LogsActivity;

    protected $fillable = [
        'transaction_id', 'kind', 'status', 'parent_id', 'number', 'financial_year', 'serial', 'invoice_date', 'billed_name', 'billed_address',
        'billed_gstin', 'place_of_supply_state_id', 'inter_state', 'sac', 'gst_applies', 'period_from', 'period_to',
        'taxable_amount', 'non_taxable_amount', 'cgst_rate', 'sgst_rate', 'igst_rate', 'cgst', 'sgst', 'igst', 'total',
        'balance_due', 'notes', 'irn', 'ack_no', 'ack_at', 'signed_qr', 'irn_cancelled_at', 'pdf_path', 'created_by', 'returned_reason',
        'returned_by', 'returned_at', 'issued_by', 'issued_at', 'cancel_reason', 'cancelled_by', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => InvoiceKind::class,
            'status' => InvoiceStatus::class,
            'invoice_date' => 'date',
            'period_from' => 'date',
            'period_to' => 'date',
            'inter_state' => 'boolean',
            'gst_applies' => 'boolean',
            'serial' => 'integer',
            'taxable_amount' => 'decimal:2',
            'non_taxable_amount' => 'decimal:2',
            'cgst_rate' => 'decimal:2',
            'sgst_rate' => 'decimal:2',
            'igst_rate' => 'decimal:2',
            'cgst' => 'decimal:2',
            'sgst' => 'decimal:2',
            'igst' => 'decimal:2',
            'total' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'ack_at' => 'datetime',
            'irn_cancelled_at' => 'datetime',
            'returned_at' => 'datetime',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** "Proforma invoice BTL/2627/INV012", or "Draft proforma invoice" before it has a number. */
    public function title(): string
    {
        return $this->number ? "{$this->kind->label()} {$this->number}" : 'Draft '.lcfirst($this->kind->label());
    }

    public function totalTax(): string
    {
        return (string) BigDecimal::of($this->cgst)->plus($this->sgst)->plus($this->igst);
    }

    /** Proformas (issued or converted) and issued reimbursement bills: money can be due on them. */
    public function isCollectable(): bool
    {
        return $this->kind->isReceivable()
            && ($this->status === InvoiceStatus::Issued || ($this->kind === InvoiceKind::Proforma && $this->status === InvoiceStatus::Converted));
    }

    /** Issued credit notes against this proforma's tax invoices. */
    public function creditedTotal(): string
    {
        if ($this->kind !== InvoiceKind::Proforma) {
            return '0.00';
        }

        return (string) BigDecimal::of((string) (Invoice::query()
            ->where('kind', InvoiceKind::CreditNote)->where('status', InvoiceStatus::Issued)
            ->whereIn('parent_id', $this->children()->select('id'))
            ->sum('total') ?: '0'))->toScale(2);
    }

    /**
     * What's still owed: the total less credit notes, receipts and TDS, worked out from the records
     * (balance_due holds the same, stored). Never below zero.
     */
    public function outstanding(): string
    {
        if (! $this->isCollectable()) {
            return '0.00';
        }

        $left = BigDecimal::of($this->total)
            ->minus($this->creditedTotal())
            ->minus((string) ($this->receipts()->whereNull('reversed_at')->sum('amount') ?: '0'))
            ->minus((string) ($this->receipts()->whereNull('reversed_at')->sum('tds_amount') ?: '0'));

        return (string) ($left->isNegative() ? BigDecimal::zero()->toScale(2) : $left->toScale(2));
    }

    /** Stores what's outstanding; call after anything that changes it. */
    public function recalculateBalance(): void
    {
        $this->forceFill(['balance_due' => $this->outstanding()])->save();
    }

    /** Unpaid past the payment terms (config billing.payment_terms_days). */
    public function isOverdue(): bool
    {
        return $this->invoice_date !== null
            && BigDecimal::of($this->balance_due)->isPositive()
            && $this->invoice_date->copy()->addDays((int) config('billing.payment_terms_days'))->isBefore(today());
    }

    /**
     * Proformas and reimbursement bills with money still due.
     *
     * @param  Builder<static>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $query->whereIn('kind', [InvoiceKind::Proforma, InvoiceKind::Reimbursement])
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Converted])
            ->where('balance_due', '>', 0);
    }

    /**
     * Due and past the payment terms.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->due()->whereDate('invoice_date', '<', today()->subDays((int) config('billing.payment_terms_days')));
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'parent_id');
    }

    /**
     * Tax invoices made from this proforma, or credit notes against this tax invoice.
     *
     * @return HasMany<Invoice, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Invoice::class, 'parent_id');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(Invoice::class, 'parent_id')->where('kind', InvoiceKind::CreditNote);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position');
    }

    /**
     * @return HasMany<InvoiceReceipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(InvoiceReceipt::class)->orderBy('received_on')->orderBy('id');
    }

    /**
     * @return HasMany<InvoiceMail, $this>
     */
    public function mails(): HasMany
    {
        return $this->hasMany(InvoiceMail::class)->latest('id');
    }

    /**
     * @return HasMany<FeeSchedulePeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(FeeSchedulePeriod::class);
    }

    /**
     * @return HasMany<DealExpense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(DealExpense::class);
    }

    /**
     * @return BelongsTo<State, $this>
     */
    public function placeOfSupply(): BelongsTo
    {
        return $this->belongsTo(State::class, 'place_of_supply_state_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'number', 'invoice_date', 'total', 'irn', 'irn_cancelled_at', 'cancel_reason', 'returned_reason'])->logOnlyDirty();
    }
}
