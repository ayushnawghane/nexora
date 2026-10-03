<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Database\Factories\CompanyGstinFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $company_id
 * @property string $gstin
 * @property int $state_id
 * @property string|null $legal_name
 * @property string|null $trade_name
 * @property Carbon|null $registered_on
 * @property bool $is_active
 */
class CompanyGstin extends Model
{
    /** @use HasFactory<CompanyGstinFactory> */
    use HasActiveFlag, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['gstin', 'state_id', 'legal_name', 'trade_name', 'registered_on', 'is_active'];

    protected function casts(): array
    {
        return ['registered_on' => 'date'];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * @return HasMany<CompanyAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CompanyAddress::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
