<?php

namespace App\GodMode\Editors;

use App\Actions\Companies\SaveCompanyGstin;
use App\GodMode\Editor;
use App\Http\Requests\Companies\CompanyGstinRequest;
use App\Models\CompanyGstin;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The GSTIN number itself stays fixed, as on the normal screen: a wrong number is deactivated and
 * the right one added.
 *
 * @extends Editor<CompanyGstin>
 */
class CompanyGstinEditor extends Editor
{
    public function key(): string
    {
        return 'company-gstin';
    }

    public function label(): string
    {
        return 'GSTIN';
    }

    public function modelClass(): string
    {
        return CompanyGstin::class;
    }

    protected function request(): string
    {
        return CompanyGstinRequest::class;
    }

    protected function routeParameters(Model $record): array
    {
        return ['company' => $record->company, 'gstin' => $record];
    }

    public function values(Model $record): array
    {
        return [
            'gstin' => $record->gstin,
            'legal_name' => $record->legal_name,
            'trade_name' => $record->trade_name,
            'registered_on' => $record->registered_on?->toDateString(),
        ];
    }

    public function fields(Model $record): array
    {
        return [
            ['name' => 'gstin', 'label' => 'GSTIN', 'type' => 'text', 'mono' => true, 'readOnly' => true, 'hint' => 'A GSTIN number can\'t be changed. Deactivate it and add the correct one.'],
            ['name' => 'legal_name', 'label' => 'Legal name', 'type' => 'text'],
            ['name' => 'trade_name', 'label' => 'Trade name', 'type' => 'text'],
            ['name' => 'registered_on', 'label' => 'Registered on', 'type' => 'date'],
        ];
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        app(SaveCompanyGstin::class)->handle($record->company, $validated, $record);
    }

    public function companyId(Model $record): int
    {
        return $record->company_id;
    }
}
