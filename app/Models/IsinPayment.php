<?php

namespace App\Models;

use App\Enums\IsinPaymentKind;
use App\Enums\IsinPaymentStatus;
use App\Enums\RedemptionBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One due date in an ISIN's interest or principal schedule, and what happened on it. A moved due
 * date keeps the original and the reason.
 *
 * @property int $id
 * @property int $deal_isin_id
 * @property IsinPaymentKind $kind
 * @property Carbon $due_on
 * @property Carbon|null $original_due_on
 * @property string|null $due_date_reason
 * @property IsinPaymentStatus $status
 * @property Carbon|null $paid_on
 * @property RedemptionBasis|null $redemption_basis
 * @property string|null $face_value
 * @property string|null $quantity
 * @property string|null $amount
 * @property string|null $remark
 * @property int|null $recorded_by
 * @property Carbon|null $recorded_at
 * @property int $created_by
 */
class IsinPayment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'kind', 'due_on', 'original_due_on', 'due_date_reason', 'status', 'paid_on', 'redemption_basis', 'face_value',
        'quantity', 'amount', 'remark', 'recorded_by', 'recorded_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'kind' => IsinPaymentKind::class,
            'status' => IsinPaymentStatus::class,
            'redemption_basis' => RedemptionBasis::class,
            'due_on' => 'date',
            'original_due_on' => 'date',
            'paid_on' => 'date',
            'recorded_at' => 'datetime',
            'face_value' => 'decimal:2',
            'quantity' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function isOverdue(): bool
    {
        return $this->status === IsinPaymentStatus::Due && $this->due_on->isBefore(today());
    }

    /**
     * @return BelongsTo<DealIsin, $this>
     */
    public function isin(): BelongsTo
    {
        return $this->belongsTo(DealIsin::class, 'deal_isin_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return HasMany<IsinReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(IsinReminder::class);
    }

    /**
     * @return MorphMany<DocumentFile, $this>
     */
    public function files(): MorphMany
    {
        return $this->morphMany(DocumentFile::class, 'attachable')->latest('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
