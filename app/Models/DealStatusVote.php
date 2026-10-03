<?php

namespace App\Models;

use App\Enums\StatusApprovalTeam;
use App\Enums\VoteDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $deal_status_request_id
 * @property int $user_id
 * @property StatusApprovalTeam $team
 * @property VoteDecision $decision
 * @property string|null $comment
 * @property Carbon|null $created_at
 */
class DealStatusVote extends Model
{
    protected $fillable = ['user_id', 'team', 'decision', 'comment'];

    protected function casts(): array
    {
        return ['team' => StatusApprovalTeam::class, 'decision' => VoteDecision::class];
    }

    /**
     * @return BelongsTo<DealStatusRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(DealStatusRequest::class, 'deal_status_request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
