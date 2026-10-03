<?php

namespace App\Models;

use App\Enums\CompanyCategory;
use App\Enums\CompanyClass;
use App\Enums\EntityType;
use App\Models\Concerns\HasActiveFlag;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $ulid
 * @property EntityType $entity_type
 * @property string|null $cin
 * @property string $name
 * @property string|null $formerly_known_as
 * @property string|null $pan
 * @property CompanyClass|null $company_class
 * @property CompanyCategory|null $category
 * @property Carbon|null $incorporated_on
 * @property bool $is_listed
 * @property bool $is_active
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasActiveFlag, HasFactory, HasUlids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'entity_type', 'cin', 'name', 'formerly_known_as', 'pan', 'company_class', 'category',
        'incorporated_on', 'is_listed', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'entity_type' => EntityType::class,
            'company_class' => CompanyClass::class,
            'category' => CompanyCategory::class,
            'incorporated_on' => 'date',
            'is_listed' => 'boolean',
        ];
    }

    /**
     * Only the public ulid column is generated; the numeric id stays the primary key.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return HasMany<CompanyGstin, $this>
     */
    public function gstins(): HasMany
    {
        return $this->hasMany(CompanyGstin::class);
    }

    /**
     * @return HasMany<CompanyAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CompanyAddress::class);
    }

    /**
     * @return HasMany<CompanyContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(CompanyContact::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Matches name, former name, CIN, PAN or any of the company's GSTINs.
     *
     * @param  Builder<static>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $like = '%'.trim($term).'%';
        $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $like)
            ->orWhere('formerly_known_as', 'like', $like)
            ->orWhere('cin', 'like', $like)
            ->orWhere('pan', 'like', $like)
            ->orWhereHas('gstins', fn (Builder $gstins) => $gstins->where('gstin', 'like', $like)));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
