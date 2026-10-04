<?php

namespace App\Models;

use App\Enums\ConditionStage;
use App\Enums\ConditionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A CP or CS item on a deal. Whoever uploads its files submits it; a different person checks it.
 *
 * @property int $id
 * @property int $transaction_id
 * @property ConditionStage $stage
 * @property int|null $condition_document_id
 * @property string $name
 * @property int|null $issuing_authority_id
 * @property Carbon|null $due_on
 * @property ConditionStatus $status
 * @property int|null $submitted_by
 * @property Carbon|null $submitted_at
 * @property int|null $checker_id
 * @property string|null $checker_comment
 * @property Carbon|null $checked_at
 * @property string|null $waived_reason
 * @property int $created_by
 */
class DealCondition extends Model
{
    use LogsActivity;

    protected $fillable = [
        'stage', 'condition_document_id', 'name', 'issuing_authority_id', 'due_on', 'status',
        'submitted_by', 'submitted_at', 'checker_id', 'checker_comment', 'checked_at', 'waived_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'stage' => ConditionStage::class,
            'status' => ConditionStatus::class,
            'due_on' => 'date',
            'submitted_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }

    public function isOverdue(): bool
    {
        return $this->due_on !== null && $this->status->isOpen() && $this->due_on->isBefore(today());
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return BelongsTo<ConditionDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ConditionDocument::class, 'condition_document_id')->withTrashed();
    }

    /**
     * @return BelongsTo<IssuingAuthority, $this>
     */
    public function issuingAuthority(): BelongsTo
    {
        return $this->belongsTo(IssuingAuthority::class)->withTrashed();
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
     * Every file ever uploaded, newest first, including removed ones.
     *
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
        return LogOptions::defaults()
            ->logOnly(['name', 'issuing_authority_id', 'due_on', 'status', 'checker_comment', 'waived_reason'])
            ->logOnlyDirty();
    }
}
