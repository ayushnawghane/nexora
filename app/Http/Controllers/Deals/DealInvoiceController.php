<?php

namespace App\Http\Controllers\Deals;

use App\Actions\Billing\DraftInvoice;
use App\Actions\Billing\ManageExpenses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\DraftInvoiceRequest;
use App\Http\Requests\Billing\ExpenseRequest;
use App\Models\DealExpense;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/** Billing from a deal: drafting a proforma or reimbursement bill, and its out-of-pocket expenses. */
class DealInvoiceController extends Controller
{
    public function store(DraftInvoiceRequest $request, Transaction $transaction, DraftInvoice $drafts): RedirectResponse
    {
        $this->authorize('viewDeal', $transaction);
        $data = $request->validated();
        $invoice = $data['kind'] === 'reimbursement'
            ? $drafts->reimbursement($transaction, $data['expenses'] ?? [], $data['notes'] ?? null, $request->user())
            : $drafts->proforma($transaction, array_map('intval', $data['periods'] ?? []), $data['expenses'] ?? [], $data['others'] ?? [], $data['notes'] ?? null, $request->user());

        return redirect()->route('invoices.show', $invoice)->with('success', 'Draft saved. Someone else issues it.');
    }

    public function storeExpense(ExpenseRequest $request, Transaction $transaction, ManageExpenses $expenses): RedirectResponse
    {
        $this->authorize('viewDeal', $transaction);
        $expenses->add($transaction, Arr::except($request->validated(), ['files']), array_values($request->file('files', [])), $request->user());

        return back()->with('success', 'Expense recorded.');
    }

    public function updateExpense(ExpenseRequest $request, Transaction $transaction, DealExpense $expense, ManageExpenses $expenses): RedirectResponse
    {
        abort_unless($expense->transaction_id === $transaction->id, 404);
        $expenses->update($expense, Arr::except($request->validated(), ['files']), array_values($request->file('files', [])), $request->user());

        return back()->with('success', 'Expense saved.');
    }

    public function destroyExpense(Request $request, Transaction $transaction, DealExpense $expense, ManageExpenses $expenses): RedirectResponse
    {
        $this->authorize('billing.raise');
        abort_unless($expense->transaction_id === $transaction->id, 404);
        $expenses->remove($expense, $request->user());

        return back()->with('success', 'Expense removed.');
    }
}
