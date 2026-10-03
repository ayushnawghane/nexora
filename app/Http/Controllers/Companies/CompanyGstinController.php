<?php

namespace App\Http\Controllers\Companies;

use App\Actions\Companies\SaveCompanyGstin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\CompanyGstinRequest;
use App\Models\Company;
use App\Models\CompanyGstin;
use Illuminate\Http\RedirectResponse;

class CompanyGstinController extends Controller
{
    public function store(CompanyGstinRequest $request, Company $company, SaveCompanyGstin $save): RedirectResponse
    {
        $save->handle($company, $request->validated());

        return back()->with('success', 'GSTIN added.');
    }

    public function update(CompanyGstinRequest $request, Company $company, CompanyGstin $gstin, SaveCompanyGstin $save): RedirectResponse
    {
        $save->handle($company, $request->validated(), $gstin);

        return back()->with('success', 'GSTIN updated.');
    }

    /** A GSTIN that active addresses bill against can't be deactivated until they're moved off it. */
    public function toggle(Company $company, CompanyGstin $gstin): RedirectResponse
    {
        $this->authorize('update', $company);

        if ($gstin->is_active && $gstin->addresses()->active()->exists()) {
            return back()->with('error', 'Active addresses use this GSTIN. Move them to another GSTIN or deactivate them first.');
        }

        $gstin->update(['is_active' => ! $gstin->is_active]);

        return back()->with('success', $gstin->is_active ? 'GSTIN activated.' : 'GSTIN deactivated.');
    }
}
