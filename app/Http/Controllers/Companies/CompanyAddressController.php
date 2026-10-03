<?php

namespace App\Http\Controllers\Companies;

use App\Enums\AddressType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\CompanyAddressRequest;
use App\Models\Company;
use App\Models\CompanyAddress;
use Illuminate\Http\RedirectResponse;

class CompanyAddressController extends Controller
{
    public function store(CompanyAddressRequest $request, Company $company): RedirectResponse
    {
        $company->addresses()->create($request->validated());

        return back()->with('success', 'Address added.');
    }

    public function update(CompanyAddressRequest $request, Company $company, CompanyAddress $address): RedirectResponse
    {
        $address->update($request->validated());

        return back()->with('success', 'Address updated.');
    }

    /** Re-activating a registered office is blocked while another one is active. */
    public function toggle(Company $company, CompanyAddress $address): RedirectResponse
    {
        $this->authorize('update', $company);

        if (! $address->is_active) {
            if ($address->type === AddressType::Registered
                && $company->addresses()->active()->where('type', AddressType::Registered)->exists()) {
                return back()->with('error', 'This company already has an active registered office.');
            }

            if ($address->company_gstin_id !== null && ! $address->gstin()->active()->exists()) {
                return back()->with('error', 'This address uses an inactive GSTIN. Edit it to use an active GSTIN first.');
            }
        }

        $address->update(['is_active' => ! $address->is_active]);

        return back()->with('success', $address->is_active ? 'Address activated.' : 'Address deactivated.');
    }
}
