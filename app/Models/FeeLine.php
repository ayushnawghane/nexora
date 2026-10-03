<?php

namespace App\Models;

use App\Enums\EscalationType;
use App\Enums\FeeAmountType;
use App\Enums\FeeBasis;
use App\Enums\FeeFrequency;
use App\Enums\FeeKind;
use App\Enums\FeeStartReference;
use App\Enums\FeeTiming;
use App\Services\Fees\FeeTerms;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $transaction_id
 * @property FeeKind $kind
 * @property FeeAmountType $amount_type
 * @property string|null $amount
 * @property string|null $percent
 * @property string $annual_amount
 * @property FeeBasis $basis
 * @property FeeFrequency $frequency
 * @property FeeStartReference $start_reference
 * @property Carbon $start_date
 * @property FeeTiming $timing
 * @property EscalationType $escalation_type
 * @property string|null $escalation_value
 * @property int|null $escalation_every_years
 */
class FeeLine extends Model
{
    use LogsActivity;

    protected $fillable = [
        'kind', 'amount_type', 'amount', 'percent', 'annual_amount', 'basis', 'frequency', 'start_reference',
        'start_date', 'timing', 'escalation_type', 'escalation_value', 'escalation_every_years',
    ];

    protected function casts(): array
    {
        return [
            'kind' => FeeKind::class,
            'amount_type' => FeeAmountType::class,
            'amount' => 'decimal:2',
            'percent' => 'decimal:6',
            'annual_amount' => 'decimal:2',
            'basis' => FeeBasis::class,
            'frequency' => FeeFrequency::class,
            'start_reference' => FeeStartReference::class,
            'start_date' => 'date',
            'timing' => FeeTiming::class,
            'escalation_type' => EscalationType::class,
            'escalation_value' => 'decimal:2',
            'escalation_every_years' => 'integer',
        ];
    }

    public function toTerms(TransactionIssueDetail $issue): FeeTerms
    {
        $start = CarbonImmutable::parse($this->start_date->toDateString());

        return new FeeTerms(
            frequency: $this->frequency,
            timing: $this->timing,
            annualAmount: $this->annual_amount,
            startDate: $start,
            endDate: $issue->maturityFrom($start),
            escalationType: $this->escalation_type,
            escalationValue: $this->escalation_value,
            escalationEveryYears: $this->escalation_every_years,
        );
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return HasMany<FeeSchedulePeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(FeeSchedulePeriod::class)->orderBy('sequence');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
