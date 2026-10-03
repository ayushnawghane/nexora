<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Vertical extends Model
{
    use HasActiveFlag, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['code', 'name', 'signatory_id', 'is_active'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function signatory(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signatory_id');
    }

    /**
     * @return HasMany<VerticalTeam, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(VerticalTeam::class);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
