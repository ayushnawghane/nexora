<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fee_line_id
 * @property int $sequence
 * @property Carbon $from_date
 * @property Carbon $to_date
 * @property Carbon $bill_date
 * @property int $days
 * @property int $days_in_year
 * @property string $base_amount
 * @property string $amount
 * @property string $financial_year
 * @property bool $prorated
 * @property int|null $invoice_id
 */
class FeeSchedulePeriod extends Model
{
    protected $fillable = [
        'sequence', 'from_date', 'to_date', 'bill_date', 'days', 'days_in_year', 'base_amount', 'amount',
        'financial_year', 'prorated',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'bill_date' => 'date',
            'days' => 'integer',
            'days_in_year' => 'integer',
            'base_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'prorated' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<FeeLine, $this>
     */
    public function feeLine(): BelongsTo
    {
        return $this->belongsTo(FeeLine::class);
    }

    /**
     * The invoice billing this period (set while a live invoice carries it).
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
