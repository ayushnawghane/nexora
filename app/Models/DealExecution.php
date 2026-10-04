<?php

namespace App\Models;

use App\Enums\ExecutionStatus;
use App\Enums\SignatoryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A deal document in execution. Whoever uploads the executed copy is its maker; a different person
 * verifies it. Verified executions are what custody picks up.
 *
 * @property int $id
 * @property int $transaction_id
 * @property int $deal_document_id
 * @property ExecutionStatus $status
 * @property string|null $place
 * @property Carbon|null $scheduled_at
 * @property SignatoryType|null $signatory_type
 * @property int|null $signatory_user_id
 * @property int|null $poa_holder_id
 * @property Carbon|null $document_date
 * @property Carbon|null $executed_on
 * @property string|null $comments
 * @property int|null $uploaded_by
 * @property Carbon|null $uploaded_at
 * @property int|null $checker_id
 * @property string|null $checker_comment
 * @property Carbon|null $checked_at
 * @property Carbon|null $picked_up_at
 * @property int|null $picked_up_by
 * @property int $created_by
 */
class DealExecution extends Model
{
    use LogsActivity;

    protected $fillable = [
        'deal_document_id', 'status', 'place', 'scheduled_at', 'signatory_type', 'signatory_user_id', 'poa_holder_id',
        'document_date', 'executed_on', 'comments', 'uploaded_by', 'uploaded_at', 'checker_id', 'checker_comment',
        'checked_at', 'picked_up_at', 'picked_up_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExecutionStatus::class,
            'signatory_type' => SignatoryType::class,
            'scheduled_at' => 'datetime',
            'document_date' => 'date',
            'executed_on' => 'date',
            'uploaded_at' => 'datetime',
            'checked_at' => 'datetime',
            'picked_up_at' => 'datetime',
        ];
    }

    /** The signatory's name, whichever kind they are. */
    public function signatoryName(): ?string
    {
        return match ($this->signatory_type) {
            SignatoryType::Internal => $this->signatoryUser?->name,
            SignatoryType::External => $this->poaHolder?->name,
            default => null,
        };
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return BelongsTo<DealDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(DealDocument::class, 'deal_document_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function signatoryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signatory_user_id');
    }

    /**
     * @return BelongsTo<PoaHolder, $this>
     */
    public function poaHolder(): BelongsTo
    {
        return $this->belongsTo(PoaHolder::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checker_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pickedUpBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_up_by');
    }

    /**
     * Every executed copy uploaded, newest first, including replaced ones.
     *
     * @return MorphMany<DocumentFile, $this>
     */
    public function files(): MorphMany
    {
        return $this->morphMany(DocumentFile::class, 'attachable')->latest('id');
    }

    /**
     * @return MorphOne<DocumentFile, $this>
     */
    public function currentFile(): MorphOne
    {
        return $this->morphOne(DocumentFile::class, 'attachable')->whereNull('removed_at')->latestOfMany();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'place', 'scheduled_at', 'signatory_type', 'signatory_user_id', 'poa_holder_id', 'document_date', 'executed_on', 'comments', 'checker_comment', 'picked_up_at'])
            ->logOnlyDirty();
    }
}
