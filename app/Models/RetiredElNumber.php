<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An EL number taken out of use by a God Mode renumbering. It can never be issued again.
 *
 * @property int $id
 * @property string $el_number
 * @property int $transaction_id
 * @property int $god_mode_change_id
 * @property Carbon $created_at
 */
class RetiredElNumber extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['el_number', 'transaction_id', 'god_mode_change_id'];

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
