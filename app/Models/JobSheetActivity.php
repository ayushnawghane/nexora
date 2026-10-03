<?php

namespace App\Models;

use App\Enums\Listing;
use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A checklist item on every deal's job sheet. `listing` limits it to listed or unlisted issues; null = all.
 *
 * @property int $id
 * @property string $name
 * @property Listing|null $listing
 * @property bool $is_active
 */
class JobSheetActivity extends Model
{
    use HasActiveFlag, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'listing', 'is_active'];

    protected function casts(): array
    {
        return ['listing' => Listing::class];
    }

    /**
     * Active activities that apply to an issue with this listing.
     *
     * @param  Builder<static>  $query
     */
    public function scopeAppliesTo(Builder $query, ?Listing $listing): void
    {
        $query->active()->where(fn (Builder $q) => $q
            ->whereNull('listing')
            ->when($listing, fn (Builder $q) => $q->orWhere('listing', $listing)));
    }

    /**
     * @return HasMany<DealJobSheetEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(DealJobSheetEntry::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
