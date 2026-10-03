<?php

namespace App\Services\CompanyLookup;

use Illuminate\Support\Facades\Cache;

/**
 * Remembers successful lookups (the live API is paid per call and registry data changes rarely).
 * Failures are never cached, so a retry after an outage goes back to the service.
 */
class CachedCompanyLookup implements CompanyLookup
{
    public function __construct(private readonly CompanyLookup $inner, private readonly int $hours) {}

    public function cin(string $cin): CinDetails
    {
        return Cache::remember("company-lookup:cin:{$cin}", now()->addHours($this->hours), fn () => $this->inner->cin($cin));
    }

    public function gstin(string $gstin): GstinDetails
    {
        return Cache::remember("company-lookup:gstin:{$gstin}", now()->addHours($this->hours), fn () => $this->inner->gstin($gstin));
    }

    public function pan(string $pan): PanDetails
    {
        return Cache::remember("company-lookup:pan:{$pan}", now()->addHours($this->hours), fn () => $this->inner->pan($pan));
    }
}
