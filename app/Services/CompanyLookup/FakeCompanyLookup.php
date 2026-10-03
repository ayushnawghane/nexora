<?php

namespace App\Services\CompanyLookup;

use App\Enums\CompanyCategory;
use App\Support\IndianIdentifiers;

/**
 * Offline driver for local work and tests (COMPANY_LOOKUP_DRIVER=fake). Answers are made up but
 * deterministic and consistent with the identifier (year from the CIN, name from the PAN).
 *
 * To try the failure paths in the UI:
 *  - a CIN ending in 000000, or a PAN / GSTIN whose PAN starts with ZZZ → "not found"
 *  - a CIN ending in 999999, or a PAN / GSTIN whose PAN starts with ERR → "service unavailable"
 *  - a GSTIN with entity number 9 (13th character) comes back as Cancelled
 */
class FakeCompanyLookup implements CompanyLookup
{
    private const NAMES = ['Asteria Infrastructure', 'Bluewater Capital', 'Crestline Housing Finance', 'Deccan Renewables', 'Everest Logistics'];

    public function cin(string $cin): CinDetails
    {
        $this->failFor(substr($cin, -6) === '000000', substr($cin, -6) === '999999', 'company');

        $suffix = match (IndianIdentifiers::cinClass($cin)?->value) {
            'public' => ' Limited',
            'private' => ' Private Limited',
            default => ' Limited',
        };

        return new CinDetails(
            cin: $cin,
            name: $this->nameFor($cin).$suffix,
            incorporatedOn: IndianIdentifiers::cinYear($cin).'-06-15',
            category: CompanyCategory::LimitedByShares,
            status: 'Active',
            email: 'secretarial@example.com',
            registeredAddress: '12 Example Road, Fort, Mumbai, Maharashtra 400001',
        );
    }

    public function gstin(string $gstin): GstinDetails
    {
        $pan = IndianIdentifiers::gstinPan($gstin);
        $this->failFor(str_starts_with($pan, 'ZZZ'), str_starts_with($pan, 'ERR'), 'GST');
        $cancelled = $gstin[12] === '9';

        return new GstinDetails(
            gstin: $gstin,
            legalName: strtoupper($this->nameFor($pan).' Private Limited'),
            tradeName: strtoupper($this->nameFor($pan)),
            registeredOn: '2017-07-01',
            status: $cancelled ? 'Cancelled' : 'Active',
            cancelledOn: $cancelled ? '2023-03-31' : null,
            constitution: 'Private Limited Company',
            principalAddress: '12 Example Road, Fort, Mumbai, Maharashtra, 400001',
        );
    }

    public function pan(string $pan): PanDetails
    {
        $this->failFor(str_starts_with($pan, 'ZZZ'), str_starts_with($pan, 'ERR'), 'PAN');

        return new PanDetails($pan, strtoupper($this->nameFor($pan).' Private Limited'));
    }

    private function nameFor(string $identifier): string
    {
        return self::NAMES[crc32($identifier) % count(self::NAMES)];
    }

    private function failFor(bool $notFound, bool $unavailable, string $what): void
    {
        if ($unavailable) {
            throw LookupFailed::unavailable();
        }
        if ($notFound) {
            throw LookupFailed::notFound($what);
        }
    }
}
