<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One email of an invoice: to whom, and who sent it (null when sent automatically on issue).
 *
 * @property int $id
 * @property int $invoice_id
 * @property string $recipients
 * @property int|null $sent_by
 */
class InvoiceMail extends Model
{
    protected $fillable = ['recipients', 'sent_by'];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
