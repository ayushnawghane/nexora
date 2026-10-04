<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * The ranking of a charge (exclusive, first pari passu, second …).
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 */
class ChargeType extends Model
{
    use HasActiveFlag, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'is_active'];

    /**
     * @return HasMany<DealSecurity, $this>
     */
    public function dealSecurities(): HasMany
    {
        return $this->hasMany(DealSecurity::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
