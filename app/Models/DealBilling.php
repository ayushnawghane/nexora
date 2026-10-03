<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Who a deal is billed to. The place of supply is the GSTIN's state when there is one,
 * otherwise the billing address's state.
 *
 * @property int $id
 * @property int $transaction_id
 * @property int $company_address_id
 * @property int|null $company_gstin_id
 * @property int $place_of_supply_state_id
 * @property int $updated_by
 */
class DealBilling extends Model
{
    use LogsActivity;

    protected $table = 'deal_billing';

    protected $fillable = ['company_address_id', 'company_gstin_id', 'place_of_supply_state_id', 'updated_by'];

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return BelongsTo<CompanyAddress, $this>
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(CompanyAddress::class, 'company_address_id');
    }

    /**
     * @return BelongsTo<CompanyGstin, $this>
     */
    public function gstin(): BelongsTo
    {
        return $this->belongsTo(CompanyGstin::class, 'company_gstin_id');
    }

    /**
     * @return BelongsTo<State, $this>
     */
    public function placeOfSupply(): BelongsTo
    {
        return $this->belongsTo(State::class, 'place_of_supply_state_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
