<?php

namespace App\Actions\Security;

use App\Enums\SecurityNature;
use App\Models\DealSecurity;
use App\Models\Pincode;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveDealSecurity
{
    /**
     * Records (or corrects) a security created under one of the deal's legal documents. Its kind
     * can't change once it has a registration, since ROC/CERSAI and pledges don't mix.
     *
     * @param  array<string, mixed>  $data  validated DealSecurityRequest data
     */
    public function handle(Transaction $deal, ?DealSecurity $security, array $data, User $actor): DealSecurity
    {
        return DB::transaction(function () use ($deal, $security, $data, $actor) {
            /** @var Transaction $locked */
            $locked = Transaction::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isOpenDeal()) {
                throw ValidationException::withMessages(['deal_document_id' => 'The securities of a closed deal can\'t be changed.']);
            }
            if ($locked->dealDocuments()->whereKey($data['deal_document_id'])->doesntExist()) {
                throw ValidationException::withMessages(['deal_document_id' => 'Choose one of this deal\'s legal documents.']);
            }

            $nature = SecurityNature::from($data['nature']);
            if ($security) {
                $security = DealSecurity::query()->whereKey($security->id)->lockForUpdate()->firstOrFail();
                if ($security->nature !== $nature && $security->registrations()->exists()) {
                    throw ValidationException::withMessages(['nature' => 'This security is registered, so its kind can\'t change.']);
                }
            }

            if (! empty($data['pincode']) && ! empty($data['state_id'])) {
                $known = Pincode::query()->where('pincode', $data['pincode'])->pluck('state_id');
                if ($known->isNotEmpty() && ! $known->contains((int) $data['state_id'])) {
                    throw ValidationException::withMessages(['pincode' => 'This pincode isn\'t in the chosen state.']);
                }
            }

            $values = [
                'deal_document_id' => (int) $data['deal_document_id'],
                'nature' => $nature,
                'asset_owner' => $data['asset_owner'],
                'owner_id_type' => $data['owner_id_type'] ?? null,
                'owner_id_number' => $data['owner_id_number'] ?? null,
                'asset_type_id' => $data['asset_type_id'] ?? null,
                'charge_type_id' => $nature === SecurityNature::Guarantee ? null : ($data['charge_type_id'] ?? null),
                'pertaining_to' => $data['pertaining_to'] ?? null,
                'is_encumbered' => $data['is_encumbered'] ?? null,
                'description' => $data['description'] ?? null,
                'address' => $data['address'] ?? null,
                'pincode' => $data['pincode'] ?? null,
                'city' => $data['city'] ?? null,
                'state_id' => $data['state_id'] ?? null,
                'form_of_securities' => $data['form_of_securities'] ?? null,
                'confirming_party' => $data['confirming_party'] ?? null,
            ];

            if ($security) {
                $security->update($values);
            } else {
                $security = $locked->securities()->create([...$values, 'created_by' => $actor->id]);
            }
            $security->securityTypes()->sync($data['security_type_ids'] ?? []);

            return $security;
        });
    }

    /**
     * Takes a security off the deal. Not once it's registered or has due diligence items.
     */
    public function remove(DealSecurity $security): void
    {
        DB::transaction(function () use ($security) {
            $deal = Transaction::query()->whereKey($security->transaction_id)->lockForUpdate()->firstOrFail();
            $locked = DealSecurity::query()->whereKey($security->id)->lockForUpdate()->firstOrFail();

            if (! $deal->isOpenDeal()) {
                throw ValidationException::withMessages(['security' => 'The securities of a closed deal can\'t be changed.']);
            }
            if ($locked->registrations()->exists()) {
                throw ValidationException::withMessages(['security' => 'This security is registered, so it can\'t be removed.']);
            }
            if ($deal->diligenceItems()->where('deal_security_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['security' => 'Due diligence items refer to this security, so it can\'t be removed.']);
            }

            $locked->delete();
        });
    }
}
