<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Someone outside Beacon who signs documents under a power of attorney. Only a POA valid on the
 * execution date can be chosen as a signatory.
 *
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $mobile
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_till
 * @property bool $is_active
 */
class PoaHolder extends Model
{
    use HasActiveFlag, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'mobile', 'valid_from', 'valid_till', 'is_active'];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_till' => 'date'];
    }

    /**
     * Active holders whose POA covers the date.
     *
     * @param  Builder<static>  $query
     */
    public function scopeValidOn(Builder $query, Carbon|string $date): void
    {
        $day = Carbon::parse($date)->toDateString();
        $query->active()
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $day))
            ->where(fn (Builder $q) => $q->whereNull('valid_till')->orWhere('valid_till', '>=', $day));
    }

    /**
     * @return HasMany<DealExecution, $this>
     */
    public function executions(): HasMany
    {
        return $this->hasMany(DealExecution::class);
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->email;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
