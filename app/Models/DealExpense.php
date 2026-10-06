<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An out-of-pocket expense (OPE) Beacon paid for a deal and bills back without GST. Once an
 * invoice bills it, it can't change or be removed until that invoice is cancelled.
 *
 * @property int $id
 * @property string $ulid
 * @property int $transaction_id
 * @property Carbon|null $incurred_on
 * @property string $description
 * @property string $amount
 * @property int|null $invoice_id
 * @property int $created_by
 * @property Carbon|null $removed_at
 * @property int|null $removed_by
 * @property int|null $legacy_id
 */
class DealExpense extends Model
{
    use HasUlids;
    use LogsActivity;

    protected $fillable = ['incurred_on', 'description', 'amount', 'invoice_id', 'created_by', 'removed_at', 'removed_by'];

    protected function casts(): array
    {
        return [
            'incurred_on' => 'date',
            'amount' => 'decimal:2',
            'removed_at' => 'datetime',
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
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
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
     * Proof of the expense (receipts, bills).
     *
     * @return MorphMany<DocumentFile, $this>
     */
    public function files(): MorphMany
    {
        return $this->morphMany(DocumentFile::class, 'attachable')->whereNull('removed_at')->oldest('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
