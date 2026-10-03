<?php

namespace App\GodMode\Editors;

use App\Actions\Deals\SaveDealBilling;
use App\GodMode\Options;
use App\GodMode\TransactionEditor;
use App\Http\Requests\Deals\DealBillingRequest;
use App\Models\CompanyAddress;
use App\Models\CompanyContact;
use App\Models\CompanyGstin;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Same checks as the Contacts & billing tab (the company's own active records, GSTIN state matches
 * the address); God Mode can also correct a closed deal.
 */
class DealBillingEditor extends TransactionEditor
{
    public function key(): string
    {
        return 'deal-billing';
    }

    public function label(): string
    {
        return 'Billing';
    }

    protected function request(): string
    {
        return DealBillingRequest::class;
    }

    /**
     * @param  Transaction  $record
     */
    public function values(Model $record): array
    {
        $billing = $record->billing()->first();

        return [
            'company_address_id' => $billing?->company_address_id,
            'company_gstin_id' => $billing?->company_gstin_id,
            'contact_ids' => $record->billingContacts()->orderBy('company_contacts.id')->pluck('company_contacts.id')->map(fn ($id) => (int) $id)->all(),
        ];
    }

    public function fields(Model $record): array
    {
        return [
            ['name' => 'company_address_id', 'label' => 'Billing address', 'type' => 'select', 'required' => true, 'numeric' => true,
                'options' => CompanyAddress::query()->active()->where('company_id', $record->company_id)->orderBy('id')->get()
                    ->map(fn (CompanyAddress $a) => ['value' => $a->id, 'label' => trim("{$a->line1}, {$a->city} {$a->pincode}")])->all()],
            ['name' => 'company_gstin_id', 'label' => 'GSTIN', 'type' => 'select', 'numeric' => true,
                'hint' => 'An address linked to a GSTIN always bills under that GSTIN.',
                'options' => Options::records(CompanyGstin::query()->where('company_id', $record->company_id), 'gstin')],
            ['name' => 'contact_ids', 'label' => 'Billing contacts', 'type' => 'multiselect', 'required' => true,
                'options' => Options::records(CompanyContact::query()->where('company_id', $record->company_id), 'name')],
        ];
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        app(SaveDealBilling::class)->handle($record, $actor, [
            'company_address_id' => (int) $validated['company_address_id'],
            'company_gstin_id' => isset($validated['company_gstin_id']) ? (int) $validated['company_gstin_id'] : null,
            'contact_ids' => array_map('intval', $validated['contact_ids']),
        ], force: true);
    }

    /** Re-applying "no billing" isn't possible, so only a change to existing billing can be undone. */
    public function canRollBack(array $before): bool
    {
        return $before['company_address_id'] !== null;
    }
}
