<?php

namespace App\Http\Controllers\Deals;

use App\Actions\Deals\SaveDealBilling;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deals\DealBillingRequest;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;

class DealBillingController extends Controller
{
    public function update(DealBillingRequest $request, Transaction $transaction, SaveDealBilling $save): RedirectResponse
    {
        $save->handle($transaction, $request->user(), [
            'company_address_id' => (int) $request->validated('company_address_id'),
            'company_gstin_id' => $request->validated('company_gstin_id') !== null ? (int) $request->validated('company_gstin_id') : null,
            'contact_ids' => array_map('intval', $request->validated('contact_ids')),
        ]);

        return redirect()->route('deals.show', ['transaction' => $transaction, 'tab' => 'billing'])->with('success', 'Billing details saved.');
    }
}
