<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Database\Factories\CompanyContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $contact_type_id
 * @property string|null $salutation
 * @property string $name
 * @property string|null $designation
 * @property string|null $department
 * @property string|null $email
 * @property string|null $mobile
 * @property string|null $landline
 * @property bool $is_active
 */
class CompanyContact extends Model
{
    /** @use HasFactory<CompanyContactFactory> */
    use HasActiveFlag, HasFactory, LogsActivity, SoftDeletes;

    public const SALUTATIONS = ['Mr', 'Ms', 'Mrs', 'Dr', 'CA', 'CS', 'Adv'];

    protected $fillable = [
        'contact_type_id', 'salutation', 'name', 'designation', 'department', 'email', 'mobile', 'landline', 'is_active',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<ContactType, $this>
     */
    public function contactType(): BelongsTo
    {
        return $this->belongsTo(ContactType::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
