<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $transaction_id
 * @property int $version
 * @property string $el_number
 * @property Carbon $el_date
 * @property string|null $body_html Rendered letter as issued; null for versions imported from Stack
 * @property string|null $pdf_path Null until an imported version's PDF is copied over
 * @property int|null $legacy_id
 * @property int|null $legacy_upload_id
 * @property string|null $reason
 * @property int $generated_by
 * @property Carbon|null $created_at
 */
class EngagementLetter extends Model
{
    protected $fillable = ['version', 'el_number', 'el_date', 'body_html', 'pdf_path', 'reason', 'generated_by'];

    protected function casts(): array
    {
        return ['el_date' => 'date', 'version' => 'integer'];
    }

    public function hasPdf(): bool
    {
        return $this->pdf_path !== null && Storage::disk('local')->exists($this->pdf_path);
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
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
