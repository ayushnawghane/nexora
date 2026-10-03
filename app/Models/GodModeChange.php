<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One God Mode correction, with the record's state before and after. Immutable: it can be created,
 * never changed or deleted. Undoing it creates another change that `reverts` this one.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property string $editor
 * @property string $subject_type
 * @property int $subject_id
 * @property int|null $transaction_id
 * @property int|null $company_id
 * @property string $reason
 * @property array<string, mixed> $before
 * @property array<string, mixed> $after
 * @property bool $can_roll_back
 * @property int|null $reverts_change_id
 * @property Carbon $created_at
 */
class GodModeChange extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'editor', 'subject_type', 'subject_id', 'transaction_id', 'company_id', 'reason',
        'before', 'after', 'can_roll_back', 'reverts_change_id',
    ];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'can_roll_back' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('God Mode changes are permanent and cannot be edited.'));
        static::deleting(fn () => throw new LogicException('God Mode changes are permanent and cannot be deleted.'));
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<GodModeChange, $this>
     */
    public function reverts(): BelongsTo
    {
        return $this->belongsTo(GodModeChange::class, 'reverts_change_id');
    }

    /**
     * The change that undid this one, if any.
     *
     * @return HasOne<GodModeChange, $this>
     */
    public function revertedBy(): HasOne
    {
        return $this->hasOne(GodModeChange::class, 'reverts_change_id');
    }
}
