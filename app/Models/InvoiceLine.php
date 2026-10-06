<?php

namespace App\Models;

use App\Enums\InvoiceLineKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One charge on an invoice. Fee lines are taxable; out-of-pocket expenses are not.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $position
 * @property InvoiceLineKind $kind
 * @property string $description
 * @property Carbon|null $period_from
 * @property Carbon|null $period_to
 * @property bool $taxable
 * @property string $amount
 * @property int|null $fee_schedule_period_id
 * @property int|null $deal_expense_id
 * @property int|null $credited_line_id
 */
class InvoiceLine extends Model
{
    protected $fillable = [
        'position', 'kind', 'description', 'period_from', 'period_to', 'taxable', 'amount', 'fee_schedule_period_id',
        'deal_expense_id', 'credited_line_id',
    ];

    protected function casts(): array
    {
        return [
            'kind' => InvoiceLineKind::class,
            'period_from' => 'date',
            'period_to' => 'date',
            'taxable' => 'boolean',
            'amount' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<FeeSchedulePeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(FeeSchedulePeriod::class, 'fee_schedule_period_id');
    }

    /**
     * @return BelongsTo<DealExpense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(DealExpense::class, 'deal_expense_id');
    }

    /**
     * The tax invoice line a credit note line reduces.
     *
     * @return BelongsTo<InvoiceLine, $this>
     */
    public function creditedLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class, 'credited_line_id');
    }

    /**
     * Credit note lines reducing this line.
     *
     * @return HasMany<InvoiceLine, $this>
     */
    public function credits(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'credited_line_id');
    }
}
