<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BeaconGstinRequest;
use App\Http\Requests\Settings\TaxRateRequest;
use App\Models\Setting;
use App\Models\State;
use App\Models\TaxRate;
use App\Support\IndianIdentifiers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('settings.view');

        $gstin = Setting::value(Setting::BEACON_GSTIN);
        $current = TaxRate::query()->inForceOn(today())->first();

        return Inertia::render('Settings/Tax', [
            'beacon' => [
                'gstin' => $gstin,
                'state' => $gstin
                    ? State::query()->where('gst_code', IndianIdentifiers::gstinStateCode($gstin))->value('name')
                    : null,
            ],
            'rates' => TaxRate::query()->with('creator:id,name')->orderByDesc('effective_from')->get()
                ->map(fn (TaxRate $rate) => [
                    'id' => $rate->id,
                    'effective_from' => $rate->effective_from->toDateString(),
                    'cgst' => $rate->cgst,
                    'sgst' => $rate->sgst,
                    'igst' => $rate->igst,
                    'status' => $rate->isScheduled() ? 'scheduled' : ($current?->is($rate) ? 'current' : 'past'),
                    'created_by' => $rate->creator?->name,
                ]),
            'can' => ['manage' => $request->user()->can('settings.manage')],
        ]);
    }

    public function updateGstin(BeaconGstinRequest $request): RedirectResponse
    {
        Setting::put(Setting::BEACON_GSTIN, $request->validated('gstin'));

        return back()->with('success', 'Beacon\'s GSTIN saved.');
    }

    public function storeRate(TaxRateRequest $request): RedirectResponse
    {
        TaxRate::query()->create([...$request->validated(), 'created_by' => $request->user()->id]);

        return back()->with('success', 'GST rate added.');
    }

    /** Only a rate that hasn't taken effect can be withdrawn; past and current rates are history. */
    public function destroyRate(TaxRate $rate): RedirectResponse
    {
        $this->authorize('settings.manage');

        if (! $rate->isScheduled()) {
            return back()->with('error', 'This rate has already taken effect and can\'t be removed. Add a new rate instead.');
        }

        $rate->delete();

        return back()->with('success', 'Scheduled GST rate withdrawn.');
    }
}
