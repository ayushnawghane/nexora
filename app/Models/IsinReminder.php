<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A reminder email sent about a scheduled payment (by the daily job, or by someone from the deal).
 *
 * @property int $id
 * @property int $isin_payment_id
 * @property Carbon $sent_on
 * @property string $recipients
 * @property int|null $sent_by
 */
class IsinReminder extends Model
{
    protected $fillable = ['sent_on', 'recipients', 'sent_by'];

    protected function casts(): array
    {
        return ['sent_on' => 'date'];
    }

    /**
     * @return BelongsTo<IsinPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(IsinPayment::class, 'isin_payment_id');
    }
}
