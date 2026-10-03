<?php

namespace App\Services\CompanyLookup;

/**
 * Looks up public registry details (MCA for CIN, GSTN for GSTIN, ITD for PAN) to pre-fill forms.
 * Results are suggestions only: nothing is saved until the user submits the normal, validated form.
 * Implementations receive identifiers that are already normalised and format-checked.
 *
 * @throws LookupFailed when the record isn't found or the service can't be reached
 */
interface CompanyLookup
{
    public function cin(string $cin): CinDetails;

    public function gstin(string $gstin): GstinDetails;

    public function pan(string $pan): PanDetails;
}
