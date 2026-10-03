<?php

namespace App\Models;

use App\Enums\Recipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $transaction_id
 * @property int $company_contact_id
 * @property Recipient $recipient
 */
class TransactionContact extends Model
{
    protected $fillable = ['company_contact_id', 'recipient'];

    protected function casts(): array
    {
        return ['recipient' => Recipient::class];
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return BelongsTo<CompanyContact, $this>
     */
    public function companyContact(): BelongsTo
    {
        return $this->belongsTo(CompanyContact::class);
    }
}
