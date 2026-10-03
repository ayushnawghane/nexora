<?php

namespace App\Http\Controllers\Companies;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\CompanyContactRequest;
use App\Models\Company;
use App\Models\CompanyContact;
use Illuminate\Http\RedirectResponse;

class CompanyContactController extends Controller
{
    public function store(CompanyContactRequest $request, Company $company): RedirectResponse
    {
        $company->contacts()->create($request->validated());

        return back()->with('success', 'Contact added.');
    }

    public function update(CompanyContactRequest $request, Company $company, CompanyContact $contact): RedirectResponse
    {
        $contact->update($request->validated());

        return back()->with('success', 'Contact updated.');
    }

    public function toggle(Company $company, CompanyContact $contact): RedirectResponse
    {
        $this->authorize('update', $company);

        $contact->update(['is_active' => ! $contact->is_active]);

        return back()->with('success', $contact->is_active ? 'Contact activated.' : 'Contact deactivated.');
    }
}
