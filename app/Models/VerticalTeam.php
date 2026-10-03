<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VerticalTeam extends Model
{
    use HasActiveFlag, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'vertical_id', 'name', 'email', 'legal_email', 'compliance_email', 'billing_email', 'signatory_id', 'is_active',
    ];

    /**
     * @return BelongsTo<Vertical, $this>
     */
    public function vertical(): BelongsTo
    {
        return $this->belongsTo(Vertical::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function signatory(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signatory_id');
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
