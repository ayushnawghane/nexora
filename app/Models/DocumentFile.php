<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A file uploaded against a deal document or a CP/CS item, kept on the private disk. Removing it
 * only marks it removed. `path` is null for a Stack file that hasn't been copied over yet.
 *
 * @property int $id
 * @property string $ulid
 * @property string $attachable_type
 * @property int $attachable_id
 * @property string|null $path
 * @property string $original_name
 * @property string|null $mime
 * @property int|null $size
 * @property int $uploaded_by
 * @property Carbon|null $removed_at
 * @property int|null $removed_by
 * @property Carbon|null $created_at
 */
class DocumentFile extends Model
{
    use HasUlids;

    /** Types accepted for documents: PDF, Word, Excel and images, up to 20 MB each. */
    public const RULES = ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:20480'];

    /** Files that can be uploaded to a CP/CS item in one go. */
    public const MAX_FILES = 10;

    /** Current (not removed) files a CP/CS item can hold. */
    public const MAX_PER_ITEM = 20;

    protected $fillable = ['path', 'original_name', 'mime', 'size', 'uploaded_by', 'removed_at', 'removed_by'];

    protected function casts(): array
    {
        return ['removed_at' => 'datetime'];
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

    public function isAvailable(): bool
    {
        return $this->path !== null;
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
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
    public function remover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    /**
     * Shape for the deal screens.
     *
     * @return array<string, mixed>
     */
    public function present(): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->original_name,
            'size' => $this->size,
            'available' => $this->isAvailable(),
            'uploaded_by' => $this->uploader->name,
            'uploaded_at' => $this->created_at?->toIso8601String(),
            'removed' => $this->removed_at !== null,
            'removed_by' => $this->remover?->name,
            'removed_at' => $this->removed_at?->toIso8601String(),
        ];
    }
}
