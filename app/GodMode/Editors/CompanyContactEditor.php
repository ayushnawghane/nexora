<?php

namespace App\GodMode\Editors;

use App\GodMode\Editor;
use App\GodMode\Options;
use App\Http\Requests\Companies\CompanyContactRequest;
use App\Models\CompanyContact;
use App\Models\ContactType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Editor<CompanyContact>
 */
class CompanyContactEditor extends Editor
{
    public function key(): string
    {
        return 'company-contact';
    }

    public function label(): string
    {
        return 'Contact';
    }

    public function modelClass(): string
    {
        return CompanyContact::class;
    }

    protected function request(): string
    {
        return CompanyContactRequest::class;
    }

    protected function routeParameters(Model $record): array
    {
        return ['company' => $record->company, 'contact' => $record];
    }

    public function values(Model $record): array
    {
        return [
            'contact_type_id' => $record->contact_type_id,
            'salutation' => $record->salutation,
            'name' => $record->name,
            'designation' => $record->designation,
            'department' => $record->department,
            'email' => $record->email,
            'mobile' => $record->mobile,
            'landline' => $record->landline,
        ];
    }

    public function fields(Model $record): array
    {
        return [
            ['name' => 'contact_type_id', 'label' => 'Contact type', 'type' => 'select', 'numeric' => true,
                'options' => Options::records(ContactType::query(), 'name', [$record->contact_type_id])],
            ['name' => 'salutation', 'label' => 'Salutation', 'type' => 'select', 'options' => Options::plain(CompanyContact::SALUTATIONS)],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['name' => 'designation', 'label' => 'Designation', 'type' => 'text'],
            ['name' => 'department', 'label' => 'Department', 'type' => 'text'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'text'],
            ['name' => 'mobile', 'label' => 'Mobile', 'type' => 'text'],
            ['name' => 'landline', 'label' => 'Landline', 'type' => 'text'],
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
