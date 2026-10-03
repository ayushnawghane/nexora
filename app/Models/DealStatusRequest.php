<?php

namespace App\Models;

use App\Enums\DealStatus;
use App\Enums\StatusApprovalTeam;
use App\Enums\StatusRequestState;
use App\Enums\VoteDecision;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $transaction_id
 * @property DealStatus $from_status
 * @property DealStatus $to_status
 * @property Carbon $effective_on
 * @property string $reason
 * @property string|null $noc_path
 * @property string|null $noc_name
 * @property bool $needs_management
 * @property bool $needs_accounts
 * @property StatusRequestState $status
 * @property int $requested_by
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 */
class DealStatusRequest extends Model
{
    use HasUlids;

    protected $fillable = [
        'from_status', 'to_status', 'effective_on', 'reason', 'noc_path', 'noc_name',
        'needs_management', 'needs_accounts', 'status', 'requested_by', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => DealStatus::class,
            'to_status' => DealStatus::class,
            'effective_on' => 'date',
            'needs_management' => 'boolean',
            'needs_accounts' => 'boolean',
            'status' => StatusRequestState::class,
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

    public function isOpen(): bool
    {
        return $this->status === StatusRequestState::Open;
    }

    /**
     * @return list<StatusApprovalTeam>
     */
    public function teams(): array
    {
        return array_values(array_filter([
            $this->needs_management ? StatusApprovalTeam::Management : null,
            $this->needs_accounts ? StatusApprovalTeam::Accounts : null,
        ]));
    }

    /**
     * Teams that still have to vote.
     *
     * @return list<StatusApprovalTeam>
     */
    public function pendingTeams(): array
    {
        $voted = $this->votes->map(fn (DealStatusVote $v) => $v->team);

        return array_values(array_filter($this->teams(), fn (StatusApprovalTeam $t) => ! $voted->contains($t)));
    }

    /** Rejected as soon as any team rejects; approved once every required team has approved. */
    public function outcome(): StatusRequestState
    {
        $votes = $this->votes()->get();

        if ($votes->contains(fn (DealStatusVote $v) => $v->decision === VoteDecision::Reject)) {
            return StatusRequestState::Rejected;
        }

        $approved = $votes->map(fn (DealStatusVote $v) => $v->team);
        foreach ($this->teams() as $team) {
            if (! $approved->contains($team)) {
                return StatusRequestState::Open;
            }
        }

        return StatusRequestState::Approved;
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return HasMany<DealStatusVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(DealStatusVote::class);
    }
}
