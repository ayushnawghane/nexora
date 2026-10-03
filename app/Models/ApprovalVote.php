<?php

namespace App\Models;

use App\Enums\VoteDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $approval_request_id
 * @property int $user_id
 * @property VoteDecision $decision
 * @property bool $is_head
 * @property string|null $comment
 * @property string $via
 * @property Carbon|null $created_at
 */
class ApprovalVote extends Model
{
    protected $fillable = ['user_id', 'decision', 'is_head', 'comment', 'via'];

    protected function casts(): array
    {
        return ['decision' => VoteDecision::class, 'is_head' => 'boolean'];
    }

    /**
     * @return BelongsTo<ApprovalRequest, $this>
     */
    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
