<?php

namespace App\Models;

use App\Enums\OwnerIdType;
use App\Enums\SecurityNature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A security created under one of the deal's legal documents: whose asset, what it covers and the
 * charge. Registrations (ROC, CERSAI, pledge) and due diligence items refer to it.
 *
 * @property int $id
 * @property int $transaction_id
 * @property int $deal_document_id
 * @property SecurityNature $nature
 * @property string $asset_owner
 * @property OwnerIdType|null $owner_id_type
 * @property string|null $owner_id_number
 * @property int|null $asset_type_id
 * @property int|null $charge_type_id
 * @property string|null $pertaining_to
 * @property bool|null $is_encumbered
 * @property string|null $description
 * @property string|null $address
 * @property string|null $pincode
 * @property string|null $city
 * @property int|null $state_id
 * @property string|null $form_of_securities
 * @property string|null $confirming_party
 * @property int $created_by
 */
class DealSecurity extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'deal_document_id', 'nature', 'asset_owner', 'owner_id_type', 'owner_id_number', 'asset_type_id', 'charge_type_id',
        'pertaining_to', 'is_encumbered', 'description', 'address', 'pincode', 'city', 'state_id', 'form_of_securities',
        'confirming_party', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'nature' => SecurityNature::class,
            'owner_id_type' => OwnerIdType::class,
            'is_encumbered' => 'boolean',
        ];
    }

    /** A one-line name for lists: what, whose. */
    public function summary(): string
    {
        $types = $this->securityTypes->pluck('name')->implode(', ');

        return trim(($types !== '' ? $types : $this->nature->label()).' · '.$this->asset_owner);
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return BelongsTo<DealDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(DealDocument::class, 'deal_document_id')->withTrashed();
    }

    /**
     * @return BelongsTo<AssetType, $this>
     */
    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ChargeType, $this>
     */
    public function chargeType(): BelongsTo
    {
        return $this->belongsTo(ChargeType::class)->withTrashed();
    }

    /**
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * @return BelongsToMany<SecurityType, $this>
     */
    public function securityTypes(): BelongsToMany
    {
        return $this->belongsToMany(SecurityType::class)->withTrashed();
    }

    /**
     * @return BelongsToMany<SecurityRegistration, $this>
     */
    public function registrations(): BelongsToMany
    {
        return $this->belongsToMany(SecurityRegistration::class, 'deal_security_registration');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
