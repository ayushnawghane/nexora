<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A firm on Beacon's panel (chartered accountants, valuers …) that issues due diligence
 * certificates such as ROC search reports and the security cover certificate.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $city
 * @property bool $is_active
 */
class EmpanelledAgency extends Model
{
    use HasActiveFlag, LogsActivity, SoftDeletes;

    protected $fillable = ['code', 'name', 'city', 'is_active'];

    /**
     * @return HasMany<DealDiligenceItem, $this>
     */
    public function diligenceItems(): HasMany
    {
        return $this->hasMany(DealDiligenceItem::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
