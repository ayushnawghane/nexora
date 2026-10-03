<?php

namespace App\Models;

use App\Enums\IssueType;
use App\Enums\Listing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $transaction_id
 * @property Listing $listing
 * @property IssueType $issue_type
 * @property bool $is_secured
 * @property bool $is_rated
 * @property string $currency
 * @property string $base_issue_size
 * @property string $green_shoe_size
 * @property string $total_issue_size
 * @property int $tenure_months
 * @property int $tenure_days
 */
class TransactionIssueDetail extends Model
{
    use LogsActivity;

    protected $fillable = [
        'listing', 'issue_type', 'is_secured', 'is_rated', 'currency', 'base_issue_size', 'green_shoe_size',
        'total_issue_size', 'tenure_months', 'tenure_days',
    ];

    protected function casts(): array
    {
        return [
            'listing' => Listing::class,
            'issue_type' => IssueType::class,
            'is_secured' => 'boolean',
            'is_rated' => 'boolean',
            'base_issue_size' => 'decimal:2',
            'green_shoe_size' => 'decimal:2',
            'total_issue_size' => 'decimal:2',
            'tenure_months' => 'integer',
            'tenure_days' => 'integer',
        ];
    }

    /** Last day covered by a fee that starts on $start: start + tenure, less one day. */
    public function maturityFrom(CarbonImmutable $start): CarbonImmutable
    {
        return $start->addMonthsNoOverflow($this->tenure_months)->addDays($this->tenure_days)->subDay();
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
