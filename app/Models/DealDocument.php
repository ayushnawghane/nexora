<?php

namespace App\Models;

use App\Enums\DealDocumentKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A legal document on one deal, with its execution version as the current file. Earlier uploads
 * stay in the file history.
 *
 * @property int $id
 * @property int $transaction_id
 * @property int $legal_document_type_id
 * @property DealDocumentKind $kind
 * @property int $sequence
 * @property string $name
 * @property int $created_by
 * @property int|null $removed_by
 */
class DealDocument extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = ['legal_document_type_id', 'kind', 'sequence', 'name', 'created_by'];

    protected function casts(): array
    {
        return ['kind' => DealDocumentKind::class];
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return BelongsTo<LegalDocumentType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(LegalDocumentType::class, 'legal_document_type_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The document's execution, once it has been sent for execution.
     *
     * @return HasOne<DealExecution, $this>
     */
    public function execution(): HasOne
    {
        return $this->hasOne(DealExecution::class);
    }

    /**
     * @return HasMany<DealSecurity, $this>
     */
    public function securities(): HasMany
    {
        return $this->hasMany(DealSecurity::class);
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
     * @return MorphOne<DocumentFile, $this>
     */
    public function currentFile(): MorphOne
    {
        return $this->morphOne(DocumentFile::class, 'attachable')->whereNull('removed_at')->latestOfMany();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'kind', 'deleted_at'])->logOnlyDirty();
    }
}
