<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model
{
    use HasActiveFlag, HasFactory, LogsActivity, SoftDeletes;

    /** Matches the legacy product code, which appears in EL numbers (BTL/DEB/EL/…) and deal codes. */
    public const DEBENTURE_TRUSTEE = 'DEB';

    protected $fillable = ['code', 'name', 'is_active'];

    /**
     * @return BelongsToMany<Vertical, $this>
     */
    public function verticals(): BelongsToMany
    {
        return $this->belongsToMany(Vertical::class);
    }

    /**
     * @return BelongsToMany<VerticalTeam, $this>
     */
    public function verticalTeams(): BelongsToMany
    {
        return $this->belongsToMany(VerticalTeam::class);
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
