<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Who issues a CP/CS document (Issuer, Statutory Auditor, ROC …).
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 */
class IssuingAuthority extends Model
{
    use HasActiveFlag, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'is_active'];

    /**
     * @return HasMany<ConditionDocument, $this>
     */
    public function conditionDocuments(): HasMany
    {
        return $this->hasMany(ConditionDocument::class);
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
