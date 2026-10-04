<?php

namespace App\GodMode\Editors;

use App\Enums\OwnerIdType;
use App\Enums\SecurityNature;
use App\GodMode\Editor;
use App\GodMode\Options;
use App\Http\Requests\Deals\Security\DealSecurityRequest;
use App\Models\AssetType;
use App\Models\ChargeType;
use App\Models\DealDocument;
use App\Models\DealSecurity;
use App\Models\SecurityType;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Corrects a security's details with the normal form's rules. Its registrations and due diligence
 * stay as they are; its kind can't change while it's registered.
 *
 * @extends Editor<DealSecurity>
 */
class DealSecurityEditor extends Editor
{
    public function key(): string
    {
        return 'deal-security';
    }

    public function label(): string
    {
        return 'Security';
    }

    public function modelClass(): string
    {
        return DealSecurity::class;
    }

    protected function request(): string
    {
        return DealSecurityRequest::class;
    }

    protected function routeParameters(Model $record): array
    {
        return ['transaction' => $record->transaction];
    }

    public function values(Model $record): array
    {
        return [
            'deal_document_id' => $record->deal_document_id,
            'nature' => $record->nature->value,
            'asset_owner' => $record->asset_owner,
            'owner_id_type' => $record->owner_id_type?->value,
            'owner_id_number' => $record->owner_id_number,
            'asset_type_id' => $record->asset_type_id,
            'charge_type_id' => $record->charge_type_id,
            'security_type_ids' => $record->securityTypes()->pluck('security_types.id')->sort()->values()->all(),
            'pertaining_to' => $record->pertaining_to,
            'is_encumbered' => $record->is_encumbered,
            'description' => $record->description,
            'address' => $record->address,
            'pincode' => $record->pincode,
            'city' => $record->city,
            'state_id' => $record->state_id,
            'form_of_securities' => $record->form_of_securities,
            'confirming_party' => $record->confirming_party,
        ];
    }

    public function fields(Model $record): array
    {
        return [
            ['name' => 'deal_document_id', 'label' => 'Legal document', 'type' => 'select', 'required' => true, 'numeric' => true,
                'options' => DealDocument::query()->where('transaction_id', $record->transaction_id)->orderBy('name')->get(['id', 'name'])
                    ->map(fn (DealDocument $d) => ['value' => $d->id, 'label' => $d->name])->all()],
            ['name' => 'nature', 'label' => 'Kind', 'type' => 'select', 'required' => true, 'options' => Options::enum(SecurityNature::class)],
            ['name' => 'asset_owner', 'label' => 'Asset owner', 'type' => 'text', 'required' => true],
            ['name' => 'owner_id_type', 'label' => 'ID type', 'type' => 'select', 'options' => Options::enum(OwnerIdType::class)],
            ['name' => 'owner_id_number', 'label' => 'CIN / PAN', 'type' => 'text'],
            ['name' => 'charge_type_id', 'label' => 'Charge', 'type' => 'select', 'numeric' => true, 'options' => Options::records(ChargeType::query(), 'name', [$record->charge_type_id])],
            ['name' => 'asset_type_id', 'label' => 'Asset type', 'type' => 'select', 'numeric' => true, 'options' => Options::records(AssetType::query(), 'name', [$record->asset_type_id])],
            ['name' => 'security_type_ids', 'label' => 'Securities over', 'type' => 'multiselect',
                'options' => Options::records(SecurityType::query(), 'name', $record->securityTypes()->pluck('security_types.id')->all())],
            ['name' => 'is_encumbered', 'label' => 'Encumbered', 'type' => 'boolean'],
            ['name' => 'pertaining_to', 'label' => 'Pertaining to', 'type' => 'text'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['name' => 'address', 'label' => 'Asset address', 'type' => 'text'],
            ['name' => 'pincode', 'label' => 'Pincode', 'type' => 'text'],
            ['name' => 'city', 'label' => 'City', 'type' => 'text'],
            ['name' => 'state_id', 'label' => 'State', 'type' => 'select', 'numeric' => true,
                'options' => State::query()->orderBy('name')->get(['id', 'name'])->map(fn (State $s) => ['value' => $s->id, 'label' => $s->name])->all()],
            ['name' => 'form_of_securities', 'label' => 'Form of securities', 'type' => 'text'],
            ['name' => 'confirming_party', 'label' => 'Confirming party', 'type' => 'text'],
        ];
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        if (DealDocument::query()->whereKey($validated['deal_document_id'])->where('transaction_id', $record->transaction_id)->doesntExist()) {
            throw ValidationException::withMessages(['deal_document_id' => 'Choose one of this deal\'s legal documents.']);
        }
        if ($validated['nature'] !== $record->nature->value && $record->registrations()->exists()) {
            throw ValidationException::withMessages(['nature' => 'This security is registered, so its kind can\'t change.']);
        }

        $record->update(collect($validated)->except('security_type_ids')->all());
        $record->securityTypes()->sync($validated['security_type_ids'] ?? []);
    }

    public function transactionId(Model $record): int
    {
        return $record->transaction_id;
    }
}
