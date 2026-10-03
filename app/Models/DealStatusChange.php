<?php

namespace App\Models;

use App\Enums\DealStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One status a deal actually moved to.
 *
 * @property int $id
 * @property int $transaction_id
 * @property DealStatus|null $from_status
 * @property DealStatus $to_status
 * @property Carbon $effective_on
 * @property int|null $deal_status_request_id
 * @property int $changed_by
 * @property Carbon|null $created_at
 */
class DealStatusChange extends Model
{
    protected $fillable = ['from_status', 'to_status', 'effective_on', 'deal_status_request_id', 'changed_by'];

    protected function casts(): array
    {
        return ['from_status' => DealStatus::class, 'to_status' => DealStatus::class, 'effective_on' => 'date'];
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
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
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
