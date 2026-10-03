<?php

namespace App\Http\Controllers\Companies;

use App\Actions\Companies\SaveCompany;
use App\Enums\AddressType;
use App\Enums\CompanyCategory;
use App\Enums\CompanyClass;
use App\Enums\EntityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\CompanyRequest;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\CompanyContact;
use App\Models\CompanyGstin;
use App\Models\ContactType;
use App\Models\State;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Company::class);

        $companies = QueryBuilder::for(Company::query()->withCount(['gstins' => fn ($q) => $q->active()]))
            ->allowedFilters(
                AllowedFilter::scope('search'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('entity_type'),
            )
            ->allowedSorts('name', 'cin', 'pan', 'created_at')
            ->defaultSort('name')
            ->paginate($request->integer('per_page', 25) > 0 ? min($request->integer('per_page', 25), 100) : 25)
            ->withQueryString()
            ->through(fn (Company $company) => [
                'id' => $company->ulid,
                'name' => $company->name,
                'formerly_known_as' => $company->formerly_known_as,
                'entity_type' => $company->entity_type->value,
                'cin' => $company->cin,
                'pan' => $company->pan,
                'is_listed' => $company->is_listed,
                'gstins_count' => $company->gstins_count,
                'is_active' => $company->is_active,
            ]);

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'filters' => [
                'search' => $request->input('filter.search', ''),
                'is_active' => $request->input('filter.is_active', ''),
                'entity_type' => $request->input('filter.entity_type', ''),
            ],
            'sort' => $request->input('sort', 'name'),
            'entityTypes' => EntityType::options(),
            'can' => ['create' => $request->user()->can('create', Company::class)],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Company::class);

        return Inertia::render('Companies/Form', ['company' => null, 'options' => $this->formOptions()]);
    }

    public function store(CompanyRequest $request, SaveCompany $saveCompany): RedirectResponse
    {
        $company = $saveCompany->handle($request->validated());

        return redirect()->route('companies.show', $company)->with('success', 'Company created. Add its GSTINs, addresses and contacts next.');
    }

    public function show(Request $request, Company $company): Response
    {
        $this->authorize('view', $company);

        $company->load([
            'gstins' => fn ($q) => $q->with('state:id,name,gst_code')->withCount('addresses')->orderByDesc('is_active')->orderBy('gstin'),
            'addresses' => fn ($q) => $q->with(['state:id,name', 'gstin:id,gstin'])->orderByDesc('is_active')->orderBy('type')->orderBy('id'),
            'contacts' => fn ($q) => $q->with('contactType:id,name')->orderByDesc('is_active')->orderBy('name'),
        ]);

        return Inertia::render('Companies/Show', [
            'company' => [
                ...$this->companyData($company),
                'class_label' => $company->company_class?->label(),
                'category_label' => $company->category?->label(),
                'entity_label' => $company->entity_type->label(),
                'created_at' => $company->created_at?->toIso8601String(),
                'updated_at' => $company->updated_at?->toIso8601String(),
            ],
            'gstins' => $company->gstins->map(fn (CompanyGstin $gstin) => [
                'id' => $gstin->id,
                'gstin' => $gstin->gstin,
                'state' => $gstin->state->name,
                'state_code' => $gstin->state->gst_code,
                'state_id' => $gstin->state_id,
                'legal_name' => $gstin->legal_name,
                'trade_name' => $gstin->trade_name,
                'registered_on' => $gstin->registered_on?->toDateString(),
                'addresses_count' => $gstin->addresses_count,
                'is_active' => $gstin->is_active,
            ]),
            'addresses' => $company->addresses->map(fn (CompanyAddress $address) => [
                'id' => $address->id,
                'type' => $address->type->value,
                'type_label' => $address->type->label(),
                'billing_name' => $address->billing_name,
                'line1' => $address->line1,
                'line2' => $address->line2,
                'city' => $address->city,
                'pincode' => $address->pincode,
                'state_id' => $address->state_id,
                'state' => $address->state->name,
                'company_gstin_id' => $address->company_gstin_id,
                'gstin' => $address->gstin?->gstin,
                'is_active' => $address->is_active,
            ]),
            'contacts' => $company->contacts->map(fn (CompanyContact $contact) => [
                'id' => $contact->id,
                'contact_type_id' => $contact->contact_type_id,
                'contact_type' => $contact->contactType?->name,
                'salutation' => $contact->salutation,
                'name' => $contact->name,
                'designation' => $contact->designation,
                'department' => $contact->department,
                'email' => $contact->email,
                'mobile' => $contact->mobile,
                'landline' => $contact->landline,
                'is_active' => $contact->is_active,
            ]),
            'options' => [
                'states' => State::query()->orderBy('name')->get(['id', 'name', 'gst_code']),
                'addressTypes' => AddressType::options(),
                'contactTypes' => ContactType::query()->active()->orderBy('name')->get(['id', 'name']),
                'salutations' => CompanyContact::SALUTATIONS,
            ],
            'can' => ['update' => $request->user()->can('update', $company)],
        ]);
    }

    public function edit(Company $company): Response
    {
        $this->authorize('update', $company);

        return Inertia::render('Companies/Form', [
            'company' => $this->companyData($company),
            'options' => $this->formOptions(),
        ]);
    }

    public function update(CompanyRequest $request, Company $company, SaveCompany $saveCompany): RedirectResponse
    {
        $saveCompany->handle($request->validated(), $company);

        return redirect()->route('companies.show', $company)->with('success', 'Company updated.');
    }

    public function toggleActive(Company $company): RedirectResponse
    {
        $this->authorize('update', $company);

        $company->update(['is_active' => ! $company->is_active]);

        return back()->with('success', $company->is_active ? 'Company activated.' : 'Company deactivated. It won\'t be offered for new transactions.');
    }

    /**
     * @return array<string, mixed>
     */
    private function companyData(Company $company): array
    {
        return [
            'id' => $company->ulid,
            'entity_type' => $company->entity_type->value,
            'cin' => $company->cin,
            'name' => $company->name,
            'formerly_known_as' => $company->formerly_known_as,
            'pan' => $company->pan,
            'company_class' => $company->company_class?->value,
            'category' => $company->category?->value,
            'incorporated_on' => $company->incorporated_on?->toDateString(),
            'is_listed' => $company->is_listed,
            'is_active' => $company->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'entityTypes' => EntityType::options(),
            'classes' => CompanyClass::options(),
            'categories' => CompanyCategory::options(),
        ];
    }
}
