<?php

namespace App\Models;

use App\Enums\RegistrationKind;
use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A ROC charge, CERSAI registration or depository pledge over some of the deal's securities. Its
 * history is in events; a satisfied (released) registration is final.
 *
 * @property int $id
 * @property int $transaction_id
 * @property RegistrationKind $kind
 * @property RegistrationStatus $status
 * @property string|null $reference
 * @property string|null $amount
 * @property string|null $security_name
 * @property int|null $quantity
 * @property string|null $face_value
 * @property string|null $depository
 * @property string|null $pledgor_dp_id
 * @property string|null $pledgor_client_id
 * @property string|null $pledgee_dp_id
 * @property string|null $pledgee_client_id
 * @property int $created_by
 */
class SecurityRegistration extends Model
{
    use LogsActivity;

    protected $fillable = [
        'kind', 'status', 'reference', 'amount', 'security_name', 'quantity', 'face_value', 'depository',
        'pledgor_dp_id', 'pledgor_client_id', 'pledgee_dp_id', 'pledgee_client_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'kind' => RegistrationKind::class,
            'status' => RegistrationStatus::class,
            'amount' => 'decimal:2',
            'face_value' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return BelongsToMany<DealSecurity, $this>
     */
    public function securities(): BelongsToMany
    {
        return $this->belongsToMany(DealSecurity::class, 'deal_security_registration')->withTrashed();
    }

    /**
     * @return HasMany<SecurityRegistrationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SecurityRegistrationEvent::class)->orderBy('happened_on')->orderBy('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
