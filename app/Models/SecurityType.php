<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * What a security is over: book debts, listed shares, land and building …
 *
 * @property int $id
 * @property string $name
 * @property int|null $asset_type_id
 * @property bool $is_active
 */
class SecurityType extends Model
{
    use HasActiveFlag, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'asset_type_id', 'is_active'];

    /**
     * @return BelongsTo<AssetType, $this>
     */
    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    /**
     * @return BelongsToMany<DealSecurity, $this>
     */
    public function dealSecurities(): BelongsToMany
    {
        return $this->belongsToMany(DealSecurity::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
