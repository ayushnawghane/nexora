<?php

namespace App\Actions\Companies;

use App\Enums\CompanyClass;
use App\Enums\EntityType;
use App\Models\Company;
use App\Support\IndianIdentifiers;

class SaveCompany
{
    /**
     * Creates or updates a company. For companies, the class and the listed flag are read from the CIN
     * (its ownership code and L/U prefix) rather than trusted from input.
     *
     * @param  array<string, mixed>  $data  validated CompanyRequest data
     */
    public function handle(array $data, ?Company $company = null): Company
    {
        $company ??= new Company;
        $type = EntityType::from($data['entity_type']);
        $cin = $data['cin'] ?? null;

        $company->fill([
            'entity_type' => $type,
            'cin' => $type === EntityType::Other ? null : $cin,
            'name' => $data['name'],
            'formerly_known_as' => $data['formerly_known_as'] ?? null,
            'pan' => $data['pan'] ?? null,
            'company_class' => $type === EntityType::Company && $cin
                ? IndianIdentifiers::cinClass($cin)
                : (isset($data['company_class']) ? CompanyClass::from($data['company_class']) : null),
            'category' => $data['category'] ?? null,
            'incorporated_on' => $data['incorporated_on'] ?? null,
            'is_listed' => $type === EntityType::Company && $cin
                ? IndianIdentifiers::isListedCin($cin)
                : (bool) ($data['is_listed'] ?? false),
        ])->save();

        return $company;
    }
}
