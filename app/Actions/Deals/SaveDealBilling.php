<?php

namespace App\Actions\Deals;

use App\Models\CompanyAddress;
use App\Models\CompanyContact;
use App\Models\CompanyGstin;
use App\Models\DealBilling;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveDealBilling
{
    /**
     * Sets who a deal is billed to. The address, GSTIN and contacts must all be the deal company's
     * active records. An address tied to a GSTIN bills under that GSTIN. The place of supply is the
     * GSTIN's state, or the address's state when there is no GSTIN; it decides CGST + SGST vs IGST.
     *
     * @param  array{company_address_id: int, company_gstin_id?: int|null, contact_ids: list<int>}  $data
     */
    public function handle(Transaction $deal, User $actor, array $data): DealBilling
    {
        if (! $deal->isOpenDeal()) {
            throw ValidationException::withMessages(['company_address_id' => 'Billing can\'t be changed on a closed deal.']);
        }

        $address = CompanyAddress::query()->active()->where('company_id', $deal->company_id)->with('state')->find($data['company_address_id'])
            ?? throw ValidationException::withMessages(['company_address_id' => 'Choose one of the company\'s active addresses.']);

        $gstinId = $data['company_gstin_id'] ?? null;
        if ($address->company_gstin_id !== null) {
            if ($gstinId !== null && $gstinId !== $address->company_gstin_id) {
                throw ValidationException::withMessages(['company_gstin_id' => 'This address is registered under another GSTIN. Use that GSTIN, or pick a different address.']);
            }
            $gstinId = $address->company_gstin_id;
        }

        $gstin = null;
        if ($gstinId !== null) {
            $gstin = CompanyGstin::query()->active()->where('company_id', $deal->company_id)->with('state')->find($gstinId)
                ?? throw ValidationException::withMessages(['company_gstin_id' => 'Choose one of the company\'s active GSTINs.']);
            if ($gstin->state_id !== $address->state_id) {
                throw ValidationException::withMessages(['company_gstin_id' => "The GSTIN is registered in {$gstin->state->name} but the address is in {$address->state->name}. Pick an address in the GSTIN's state."]);
            }
        }

        $contactIds = array_values(array_unique($data['contact_ids']));
        $contacts = CompanyContact::query()->active()->where('company_id', $deal->company_id)->whereKey($contactIds)->get();
        if ($contacts->count() !== count($contactIds)) {
            throw ValidationException::withMessages(['contact_ids' => 'Choose the company\'s active contacts only.']);
        }
        if ($contacts->every(fn (CompanyContact $c) => blank($c->email))) {
            throw ValidationException::withMessages(['contact_ids' => 'At least one billing contact needs an email address.']);
        }

        return DB::transaction(function () use ($deal, $actor, $address, $gstin, $contactIds) {
            $billing = $deal->billing()->updateOrCreate([], [
                'company_address_id' => $address->id,
                'company_gstin_id' => $gstin?->id,
                'place_of_supply_state_id' => $gstin->state_id ?? $address->state_id,
                'updated_by' => $actor->id,
            ]);
            $deal->billingContacts()->sync($contactIds);

            return $billing;
        });
    }
}
