<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
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
 * @property ApprovalStatus $status
 * @property int $requested_by
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property-read int|null $approvals_count
 */
class ApprovalRequest extends Model
{
    use HasUlids;

    /** Approval needs a head approver plus at least this many approvals in total. */
    public const REQUIRED_APPROVALS = 2;

    protected $fillable = ['status', 'requested_by', 'closed_at'];

    protected function casts(): array
    {
        return ['status' => ApprovalStatus::class, 'closed_at' => 'datetime'];
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
        return $this->status === ApprovalStatus::Open;
    }

    /**
     * The outcome the current votes produce: rejected as soon as anyone rejects; approved once
     * a head approver and at least one other person have approved; otherwise still open.
     */
    public function outcome(): ApprovalStatus
    {
        $votes = $this->votes()->get();

        if ($votes->contains(fn (ApprovalVote $v) => $v->decision === VoteDecision::Reject)) {
            return ApprovalStatus::Rejected;
        }

        $approvals = $votes->filter(fn (ApprovalVote $v) => $v->decision === VoteDecision::Approve);

        return $approvals->contains(fn (ApprovalVote $v) => $v->is_head) && $approvals->count() >= self::REQUIRED_APPROVALS
            ? ApprovalStatus::Approved
            : ApprovalStatus::Open;
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
     * @return HasMany<ApprovalVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(ApprovalVote::class);
    }
}
