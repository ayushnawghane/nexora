<?php

namespace App\Models;

use App\Enums\RegistrationAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * One filing in a registration's history (created, modified, satisfied), with its documents
 * (challan, signed form, certificate, lender NOC …). Events are never edited or deleted.
 *
 * @property int $id
 * @property int $security_registration_id
 * @property RegistrationAction $action
 * @property Carbon $happened_on
 * @property string|null $filing_reference
 * @property string|null $amount
 * @property string|null $reason
 * @property int $created_by
 * @property Carbon|null $created_at
 */
class SecurityRegistrationEvent extends Model
{
    protected $fillable = ['action', 'happened_on', 'filing_reference', 'amount', 'reason', 'created_by'];

    protected function casts(): array
    {
        return [
            'action' => RegistrationAction::class,
            'happened_on' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<SecurityRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(SecurityRegistration::class, 'security_registration_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return MorphMany<DocumentFile, $this>
     */
    public function files(): MorphMany
    {
        return $this->morphMany(DocumentFile::class, 'attachable')->oldest('id');
    }
}
