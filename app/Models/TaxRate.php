<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Rates are percentages stored as decimal strings ("9.00"); do maths on them with brick/math.
 *
 * @property int $id
 * @property Carbon $effective_from
 * @property string $cgst
 * @property string $sgst
 * @property string $igst
 * @property int|null $created_by
 */
class TaxRate extends Model
{
    use LogsActivity;

    protected $fillable = ['effective_from', 'cgst', 'sgst', 'igst', 'created_by'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'cgst' => 'decimal:2',
            'sgst' => 'decimal:2',
            'igst' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The rate in force on a date: the latest one that had taken effect by then.
     *
     * @param  Builder<static>  $query
     */
    public function scopeInForceOn(Builder $query, \DateTimeInterface $date): void
    {
        $query->where('effective_from', '<=', Carbon::instance($date)->toDateString())->orderByDesc('effective_from');
    }

    /** A rate that hasn't taken effect yet can still be withdrawn; one in force or past can't. */
    public function isScheduled(): bool
    {
        return $this->effective_from->isAfter(today());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
