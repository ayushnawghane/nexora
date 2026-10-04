<?php

namespace App\Models;

use App\Enums\AllotmentKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Debentures allotted under an ISIN. The amount is face value × quantity allotted, worked out
 * when it's saved. The depository credit confirmation is attached as a file.
 *
 * @property int $id
 * @property int $deal_isin_id
 * @property AllotmentKind $kind
 * @property Carbon $allotment_date
 * @property Carbon|null $issue_opened_on
 * @property Carbon|null $issue_closed_on
 * @property string $face_value
 * @property int|null $quantity_offered
 * @property int $quantity_allotted
 * @property string $amount
 * @property string|null $credit_depository
 * @property Carbon|null $credited_on
 * @property int $created_by
 */
class IsinAllotment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'kind', 'allotment_date', 'issue_opened_on', 'issue_closed_on', 'face_value', 'quantity_offered',
        'quantity_allotted', 'amount', 'credit_depository', 'credited_on', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'kind' => AllotmentKind::class,
            'allotment_date' => 'date',
            'issue_opened_on' => 'date',
            'issue_closed_on' => 'date',
            'credited_on' => 'date',
            'face_value' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<DealIsin, $this>
     */
    public function isin(): BelongsTo
    {
        return $this->belongsTo(DealIsin::class, 'deal_isin_id');
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
