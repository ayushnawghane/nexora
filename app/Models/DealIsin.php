<?php

namespace App\Models;

use App\Enums\CouponType;
use App\Enums\DayCount;
use App\Enums\HolidayConvention;
use App\Enums\IsinPaymentKind;
use App\Enums\IsinPaymentStatus;
use App\Enums\Listing;
use App\Enums\PaymentFrequency;
use App\Enums\Placement;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A series of debentures under the deal, identified by its ISIN, with its allotments and its
 * interest and principal schedule.
 *
 * @property int $id
 * @property string $ulid
 * @property int $transaction_id
 * @property string $isin
 * @property string|null $series_name
 * @property Listing|null $listing
 * @property string|null $exchange
 * @property string|null $depository
 * @property Placement|null $placement
 * @property Carbon|null $allotment_date
 * @property Carbon|null $maturity_date
 * @property CouponType|null $coupon_type
 * @property string|null $coupon_rate
 * @property string|null $coupon_description
 * @property PaymentFrequency|null $interest_frequency
 * @property PaymentFrequency|null $principal_frequency
 * @property DayCount|null $day_count
 * @property HolidayConvention|null $holiday_convention
 * @property Carbon|null $put_date
 * @property Carbon|null $call_date
 * @property string|null $comments
 * @property int $created_by
 */
class DealIsin extends Model
{
    use HasUlids, LogsActivity;

    protected $fillable = [
        'isin', 'series_name', 'listing', 'exchange', 'depository', 'placement', 'allotment_date', 'maturity_date',
        'coupon_type', 'coupon_rate', 'coupon_description', 'interest_frequency', 'principal_frequency', 'day_count',
        'holiday_convention', 'put_date', 'call_date', 'comments', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'listing' => Listing::class,
            'placement' => Placement::class,
            'coupon_type' => CouponType::class,
            'interest_frequency' => PaymentFrequency::class,
            'principal_frequency' => PaymentFrequency::class,
            'day_count' => DayCount::class,
            'holiday_convention' => HolidayConvention::class,
            'allotment_date' => 'date',
            'maturity_date' => 'date',
            'put_date' => 'date',
            'call_date' => 'date',
            'coupon_rate' => 'decimal:4',
        ];
    }

    /**
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

    /** Whether every principal payment is settled (paid or redeemed early). */
    public function isRedeemed(): bool
    {
        $principal = $this->payments->where('kind', IsinPaymentKind::Principal);

        return $principal->isNotEmpty() && $principal->every(fn (IsinPayment $p) => in_array($p->status, [IsinPaymentStatus::Paid, IsinPaymentStatus::RedeemedEarly], true));
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return HasMany<IsinAllotment, $this>
     */
    public function allotments(): HasMany
    {
        return $this->hasMany(IsinAllotment::class)->orderBy('allotment_date')->orderBy('id');
    }

    /**
     * @return HasMany<IsinPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(IsinPayment::class)->orderBy('due_on')->orderBy('kind');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
