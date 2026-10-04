<?php

namespace App\Models;

use App\Enums\ConditionStatus;
use App\Enums\DiligenceKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A due diligence document of the deal. It works like a CP/CS item: whoever uploads its files sends
 * it for checking, and a different person verifies it or sends it back.
 *
 * @property int $id
 * @property int $transaction_id
 * @property DiligenceKind $kind
 * @property string $title
 * @property int|null $deal_security_id
 * @property string|null $asset_owner
 * @property int|null $empanelled_agency_id
 * @property string|null $reference
 * @property ConditionStatus $status
 * @property int|null $submitted_by
 * @property Carbon|null $submitted_at
 * @property int|null $checker_id
 * @property string|null $checker_comment
 * @property Carbon|null $checked_at
 * @property int $created_by
 */
class DealDiligenceItem extends Model
{
    use LogsActivity;

    protected $fillable = [
        'kind', 'title', 'deal_security_id', 'asset_owner', 'empanelled_agency_id', 'reference', 'status',
        'submitted_by', 'submitted_at', 'checker_id', 'checker_comment', 'checked_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'kind' => DiligenceKind::class,
            'status' => ConditionStatus::class,
            'submitted_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return BelongsTo<DealSecurity, $this>
     */
    public function security(): BelongsTo
    {
        return $this->belongsTo(DealSecurity::class, 'deal_security_id')->withTrashed();
    }

    /**
     * @return BelongsTo<EmpanelledAgency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(EmpanelledAgency::class, 'empanelled_agency_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checker_id');
    }

    /**
     * @return MorphMany<DocumentFile, $this>
     */
    public function files(): MorphMany
    {
        return $this->morphMany(DocumentFile::class, 'attachable')->latest('id');
    }

    /**
     * @return MorphMany<DocumentFile, $this>
     */
    public function currentFiles(): MorphMany
    {
        return $this->morphMany(DocumentFile::class, 'attachable')->whereNull('removed_at')->oldest('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'empanelled_agency_id', 'reference', 'status', 'checker_comment'])->logOnlyDirty();
    }
}
