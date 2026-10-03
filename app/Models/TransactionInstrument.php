<?php

namespace App\Models;

use App\Enums\Instrument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $transaction_id
 * @property Instrument $instrument
 * @property string $base_amount
 * @property string $green_shoe_amount
 */
class TransactionInstrument extends Model
{
    protected $fillable = ['instrument', 'base_amount', 'green_shoe_amount'];

    protected function casts(): array
    {
        return [
            'instrument' => Instrument::class,
            'base_amount' => 'decimal:2',
            'green_shoe_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
