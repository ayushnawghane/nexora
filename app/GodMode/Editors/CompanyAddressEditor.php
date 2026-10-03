<?php

namespace App\GodMode\Editors;

use App\Enums\AddressType;
use App\GodMode\Editor;
use App\GodMode\Options;
use App\Http\Requests\Companies\CompanyAddressRequest;
use App\Models\CompanyAddress;
use App\Models\CompanyGstin;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Editor<CompanyAddress>
 */
class CompanyAddressEditor extends Editor
{
    public function key(): string
    {
        return 'company-address';
    }

    public function label(): string
    {
        return 'Address';
    }

    public function modelClass(): string
    {
        return CompanyAddress::class;
    }

    protected function request(): string
    {
        return CompanyAddressRequest::class;
    }

    protected function routeParameters(Model $record): array
    {
        return ['company' => $record->company, 'address' => $record];
    }

    public function values(Model $record): array
    {
        return [
            'type' => $record->type->value,
            'billing_name' => $record->billing_name,
            'line1' => $record->line1,
            'line2' => $record->line2,
            'city' => $record->city,
            'pincode' => $record->pincode,
            'state_id' => $record->state_id,
            'company_gstin_id' => $record->company_gstin_id,
        ];
    }

    public function fields(Model $record): array
    {
        return [
            ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'options' => Options::enum(AddressType::class)],
            ['name' => 'billing_name', 'label' => 'Billing name', 'type' => 'text'],
            ['name' => 'line1', 'label' => 'Address line 1', 'type' => 'text', 'required' => true],
            ['name' => 'line2', 'label' => 'Address line 2', 'type' => 'text'],
            ['name' => 'city', 'label' => 'City', 'type' => 'text', 'required' => true],
            ['name' => 'pincode', 'label' => 'Pincode', 'type' => 'text', 'required' => true, 'mono' => true],
            ['name' => 'state_id', 'label' => 'State', 'type' => 'select', 'required' => true, 'numeric' => true,
                'options' => State::query()->orderBy('name')->get(['id', 'name'])->map(fn (State $s) => ['value' => $s->id, 'label' => $s->name])->all()],
            ['name' => 'company_gstin_id', 'label' => 'GSTIN', 'type' => 'select', 'numeric' => true,
                'options' => Options::records(CompanyGstin::query()->where('company_id', $record->company_id), 'gstin', [$record->company_gstin_id])],
        ];
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        $record->update($validated);
    }

    public function companyId(Model $record): int
    {
        return $record->company_id;
    }
}
