<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_active
 */
class ContactType extends Model
{
    use HasActiveFlag, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'is_active'];

    /**
     * @return HasMany<CompanyContact, $this>
     */
    public function companyContacts(): HasMany
    {
        return $this->hasMany(CompanyContact::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
