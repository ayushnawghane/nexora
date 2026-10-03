<?php

namespace App\Models;

use App\Enums\AddressType;
use App\Models\Concerns\HasActiveFlag;
use Database\Factories\CompanyAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $company_id
 * @property AddressType $type
 * @property string|null $billing_name
 * @property string $line1
 * @property string|null $line2
 * @property string $city
 * @property string $pincode
 * @property int $state_id
 * @property int|null $company_gstin_id
 * @property bool $is_active
 */
class CompanyAddress extends Model
{
    /** @use HasFactory<CompanyAddressFactory> */
    use HasActiveFlag, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'type', 'billing_name', 'line1', 'line2', 'city', 'pincode', 'state_id', 'company_gstin_id', 'is_active',
    ];

    protected function casts(): array
    {
        return ['type' => AddressType::class];
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
     * @return BelongsTo<CompanyGstin, $this>
     */
    public function gstin(): BelongsTo
    {
        return $this->belongsTo(CompanyGstin::class, 'company_gstin_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
