<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Money received against a tax invoice or reimbursement bill, with the TDS the client deducted.
 * A wrong receipt is reversed (with a reason), never deleted.
 *
 * @property int $id
 * @property string $ulid
 * @property int $invoice_id
 * @property Carbon $received_on
 * @property string $amount
 * @property string $tds_amount
 * @property string|null $utr
 * @property string|null $remark
 * @property int $created_by
 * @property string|null $reversal_reason
 * @property int|null $reversed_by
 * @property Carbon|null $reversed_at
 * @property int|null $legacy_id
 */
class InvoiceReceipt extends Model
{
    use HasUlids;
    use LogsActivity;

    protected $fillable = ['received_on', 'amount', 'tds_amount', 'utr', 'remark', 'created_by', 'reversal_reason', 'reversed_by', 'reversed_at'];

    protected function casts(): array
    {
        return [
            'received_on' => 'date',
            'amount' => 'decimal:2',
            'tds_amount' => 'decimal:2',
            'reversed_at' => 'datetime',
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

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
