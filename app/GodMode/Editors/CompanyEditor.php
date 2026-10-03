<?php

namespace App\GodMode\Editors;

use App\Actions\Companies\SaveCompany;
use App\Enums\CompanyCategory;
use App\Enums\CompanyClass;
use App\Enums\EntityType;
use App\GodMode\Editor;
use App\GodMode\Options;
use App\Http\Requests\Companies\CompanyRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Editor<Company>
 */
class CompanyEditor extends Editor
{
    public function key(): string
    {
        return 'company';
    }

    public function label(): string
    {
        return 'Company';
    }

    public function modelClass(): string
    {
        return Company::class;
    }

    protected function request(): string
    {
        return CompanyRequest::class;
    }

    protected function routeParameters(Model $record): array
    {
        return ['company' => $record];
    }

    public function values(Model $record): array
    {
        return [
            'entity_type' => $record->entity_type->value,
            'cin' => $record->cin,
            'name' => $record->name,
            'formerly_known_as' => $record->formerly_known_as,
            'pan' => $record->pan,
            'company_class' => $record->company_class?->value,
            'category' => $record->category?->value,
            'incorporated_on' => $record->incorporated_on?->toDateString(),
            'is_listed' => (bool) $record->is_listed,
        ];
    }

    public function fields(Model $record): array
    {
        return [
            ['name' => 'entity_type', 'label' => 'Entity type', 'type' => 'select', 'required' => true, 'options' => Options::enum(EntityType::class)],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['name' => 'cin', 'label' => 'CIN / LLPIN', 'type' => 'text', 'mono' => true],
            ['name' => 'pan', 'label' => 'PAN', 'type' => 'text', 'mono' => true],
            ['name' => 'formerly_known_as', 'label' => 'Formerly known as', 'type' => 'text'],
            ['name' => 'company_class', 'label' => 'Class', 'type' => 'select', 'options' => Options::enum(CompanyClass::class), 'hint' => 'For companies this is read from the CIN.'],
            ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => Options::enum(CompanyCategory::class)],
            ['name' => 'incorporated_on', 'label' => 'Incorporated on', 'type' => 'date'],
            ['name' => 'is_listed', 'label' => 'Listed', 'type' => 'boolean', 'hint' => 'For companies this is read from the CIN.'],
        ];
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        app(SaveCompany::class)->handle($validated, $record);
    }

    public function companyId(Model $record): int
    {
        return $record->id;
    }
}
