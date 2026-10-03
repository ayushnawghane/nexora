<?php

namespace App\Actions\Companies;

use App\Models\Company;
use App\Models\CompanyGstin;
use App\Models\State;
use App\Support\IndianIdentifiers;

class SaveCompanyGstin
{
    /**
     * Adds a GSTIN (state taken from its first two digits) or updates an existing one's details.
     *
     * @param  array<string, mixed>  $data  validated CompanyGstinRequest data
     */
    public function handle(Company $company, array $data, ?CompanyGstin $gstin = null): CompanyGstin
    {
        $details = [
            'legal_name' => $data['legal_name'] ?? null,
            'trade_name' => $data['trade_name'] ?? null,
            'registered_on' => $data['registered_on'] ?? null,
        ];

        if ($gstin) {
            $gstin->update($details);

            return $gstin;
        }

        $state = State::query()->where('gst_code', IndianIdentifiers::gstinStateCode($data['gstin']))->firstOrFail();

        return $company->gstins()->create([...$details, 'gstin' => $data['gstin'], 'state_id' => $state->id]);
    }
}
