<?php

namespace App\Models;

use App\Enums\DealStatus;
use App\Enums\Origin;
use App\Enums\TransactionStatus;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LogicException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A trusteeship deal from draft to closure. `status` only changes through transitionTo().
 *
 * @property int $id
 * @property string $ulid
 * @property int $product_id
 * @property int $company_id
 * @property TransactionStatus $status
 * @property DealStatus|null $deal_status
 * @property Carbon|null $deal_status_since
 * @property string|null $el_number
 * @property Carbon|null $el_date
 * @property string|null $deal_code
 * @property int|null $transaction_type_id
 * @property int|null $lead_source_id
 * @property int|null $arranger_id
 * @property int|null $vertical_team_id
 * @property int|null $relationship_manager_id
 * @property int|null $signatory_id
 * @property Origin $origin
 * @property string|null $brief
 * @property Carbon|null $schedule_verified_at
 * @property int|null $schedule_verified_by
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $closed_at
 * @property int $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'product_id', 'company_id', 'transaction_type_id', 'lead_source_id', 'arranger_id', 'vertical_team_id',
        'relationship_manager_id', 'signatory_id', 'origin', 'brief', 'created_by', 'updated_by',
    ];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'status' => TransactionStatus::class,
            'deal_status' => DealStatus::class,
            'deal_status_since' => 'date',
            'origin' => Origin::class,
            'el_date' => 'date',
            'schedule_verified_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
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

    /** Moves to the next status, refusing any transition the state machine doesn't allow. */
    public function transitionTo(TransactionStatus $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw new LogicException("A {$this->status->label()} transaction can't become {$next->label()}.");
        }

        $this->status = $next;
    }

    /** Any change to the issue or fees makes the verified schedule stale. */
    public function markScheduleUnverified(): void
    {
        $this->schedule_verified_at = null;
        $this->schedule_verified_by = null;
    }

    public function isScheduleVerified(): bool
    {
        return $this->schedule_verified_at !== null;
    }

    /** A deal exists once the engagement letter has been issued. */
    public function isDeal(): bool
    {
        return $this->deal_status !== null;
    }

    /** Deal records (billing, job sheet, status) can still change through the normal screens. */
    public function isOpenDeal(): bool
    {
        return $this->deal_status !== null && ! $this->deal_status->isFinal();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeInStatus(Builder $query, TransactionStatus ...$statuses): void
    {
        $query->whereIn('status', array_map(fn (TransactionStatus $s) => $s->value, $statuses));
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<TransactionType, $this>
     */
    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class);
    }

    /**
     * @return BelongsTo<LeadSource, $this>
     */
    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class);
    }

    /**
     * @return BelongsTo<Arranger, $this>
     */
    public function arranger(): BelongsTo
    {
        return $this->belongsTo(Arranger::class);
    }

    /**
     * @return BelongsTo<VerticalTeam, $this>
     */
    public function verticalTeam(): BelongsTo
    {
        return $this->belongsTo(VerticalTeam::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function relationshipManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'relationship_manager_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function signatory(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signatory_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function scheduleVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'schedule_verified_by');
    }

    /**
     * @return HasOne<TransactionIssueDetail, $this>
     */
    public function issueDetail(): HasOne
    {
        return $this->hasOne(TransactionIssueDetail::class);
    }

    /**
     * @return HasMany<TransactionInstrument, $this>
     */
    public function instruments(): HasMany
    {
        return $this->hasMany(TransactionInstrument::class);
    }

    /**
     * @return HasMany<TransactionContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(TransactionContact::class);
    }

    /**
     * @return HasMany<FeeLine, $this>
     */
    public function feeLines(): HasMany
    {
        return $this->hasMany(FeeLine::class);
    }

    /**
     * @return HasManyThrough<FeeSchedulePeriod, FeeLine, $this>
     */
    public function schedulePeriods(): HasManyThrough
    {
        return $this->hasManyThrough(FeeSchedulePeriod::class, FeeLine::class);
    }

    /**
     * @return HasMany<ApprovalRequest, $this>
     */
    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class);
    }

    /**
     * @return HasOne<ApprovalRequest, $this>
     */
    public function latestApprovalRequest(): HasOne
    {
        return $this->hasOne(ApprovalRequest::class)->latestOfMany();
    }

    /**
     * @return HasMany<EngagementLetter, $this>
     */
    public function engagementLetters(): HasMany
    {
        return $this->hasMany(EngagementLetter::class)->orderByDesc('version');
    }

    /**
     * @return HasMany<DealStatusRequest, $this>
     */
    public function statusRequests(): HasMany
    {
        return $this->hasMany(DealStatusRequest::class);
    }

    /**
     * @return HasMany<DealStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(DealStatusChange::class);
    }

    /**
     * @return HasOne<DealBilling, $this>
     */
    public function billing(): HasOne
    {
        return $this->hasOne(DealBilling::class);
    }

    /**
     * Company contacts who receive this deal's invoices.
     *
     * @return BelongsToMany<CompanyContact, $this>
     */
    public function billingContacts(): BelongsToMany
    {
        return $this->belongsToMany(CompanyContact::class, 'deal_billing_contacts')->withTimestamps();
    }

    /**
     * @return HasMany<DealJobSheetEntry, $this>
     */
    public function jobSheetEntries(): HasMany
    {
        return $this->hasMany(DealJobSheetEntry::class);
    }

    /**
     * @return HasMany<DealDocument, $this>
     */
    public function dealDocuments(): HasMany
    {
        return $this->hasMany(DealDocument::class);
    }

    /**
     * @return HasMany<DealCondition, $this>
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(DealCondition::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([...$this->fillable, 'status', 'deal_status', 'el_number', 'el_date', 'deal_code', 'schedule_verified_at'])
            ->logOnlyDirty();
    }
}
