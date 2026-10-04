<?php

namespace App\Models;

use App\Enums\ConditionStage;
use App\Enums\Listing;
use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A CP or CS document from the master list. The four flags say which kind of issue it's suggested for.
 *
 * @property int $id
 * @property ConditionStage $stage
 * @property string $name
 * @property int|null $issuing_authority_id
 * @property bool $listed_secured
 * @property bool $listed_unsecured
 * @property bool $unlisted_secured
 * @property bool $unlisted_unsecured
 * @property bool $is_active
 */
class ConditionDocument extends Model
{
    use HasActiveFlag, LogsActivity, SoftDeletes;

    protected $fillable = [
        'stage', 'name', 'issuing_authority_id', 'listed_secured', 'listed_unsecured',
        'unlisted_secured', 'unlisted_unsecured', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'stage' => ConditionStage::class,
            'listed_secured' => 'boolean',
            'listed_unsecured' => 'boolean',
            'unlisted_secured' => 'boolean',
            'unlisted_unsecured' => 'boolean',
        ];
    }

    /** Whether the document is suggested for an issue with this listing and security. */
    public function isSuggestedFor(?Listing $listing, ?bool $secured): bool
    {
        if ($listing === null || $secured === null) {
            return false;
        }

        return (bool) $this->getAttribute(($listing === Listing::Listed ? 'listed_' : 'unlisted_').($secured ? 'secured' : 'unsecured'));
    }

    /**
     * @return BelongsTo<IssuingAuthority, $this>
     */
    public function issuingAuthority(): BelongsTo
    {
        return $this->belongsTo(IssuingAuthority::class);
    }

    /**
     * @return HasMany<DealCondition, $this>
     */
    public function dealConditions(): HasMany
    {
        return $this->hasMany(DealCondition::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
