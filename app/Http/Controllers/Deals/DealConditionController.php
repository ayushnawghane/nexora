<?php

namespace App\Http\Controllers\Deals;

use App\Actions\Documents\AddDealConditions;
use App\Actions\Documents\CheckCondition;
use App\Actions\Documents\UpdateDealCondition;
use App\Actions\Documents\UploadConditionFiles;
use App\Enums\ConditionStage;
use App\Enums\ConditionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deals\Documents\DealConditionCheckRequest;
use App\Http\Requests\Deals\Documents\DealConditionDueDateRequest;
use App\Http\Requests\Deals\Documents\DealConditionFilesRequest;
use App\Http\Requests\Deals\Documents\DealConditionStoreRequest;
use App\Http\Requests\Deals\Documents\DealConditionWaiveRequest;
use App\Models\DealCondition;
use App\Models\IssuingAuthority;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** A deal's conditions precedent and subsequent (CP/CS). */
class DealConditionController extends Controller
{
    public function store(DealConditionStoreRequest $request, Transaction $transaction, AddDealConditions $add): RedirectResponse
    {
        $stage = ConditionStage::from($request->validated('stage'));
        $dueOn = $request->validated('due_on') ? CarbonImmutable::parse($request->validated('due_on')) : null;

        if ($request->validated('source') === 'master') {
            $added = $add->fromMaster($transaction, $stage, array_map('intval', $request->validated('document_ids')), $dueOn, $request->user());

            return back()->with('success', $added->count() === 1 ? "{$stage->short()} item added." : "{$added->count()} {$stage->short()} items added.");
        }

        $authorityId = $request->validated('issuing_authority_id');
        $add->custom($transaction, $stage, $request->validated('name'), $authorityId ? IssuingAuthority::query()->findOrFail($authorityId) : null, $dueOn, $request->user());

        return back()->with('success', "{$stage->short()} item added.");
    }

    public function upload(DealConditionFilesRequest $request, Transaction $transaction, DealCondition $condition, UploadConditionFiles $upload): RedirectResponse
    {
        abort_unless($condition->transaction_id === $transaction->id, 404);

        $upload->handle($condition, array_values($request->file('files')), $request->user());

        return back()->with('success', 'Files uploaded and sent for checking.');
    }

    public function check(DealConditionCheckRequest $request, Transaction $transaction, DealCondition $condition, CheckCondition $check): RedirectResponse
    {
        abort_unless($condition->transaction_id === $transaction->id, 404);

        $result = $check->handle($condition, $request->user(), ConditionStatus::from($request->validated('decision')), $request->validated('comment'));

        return back()->with('success', $result->status === ConditionStatus::Verified ? 'Item verified.' : 'Item sent back.');
    }

    public function dueDate(DealConditionDueDateRequest $request, Transaction $transaction, DealCondition $condition, UpdateDealCondition $update): RedirectResponse
    {
        abort_unless($condition->transaction_id === $transaction->id, 404);

        $dueOn = $request->validated('due_on');
        $update->setDueDate($condition, $dueOn ? CarbonImmutable::parse($dueOn) : null);

        return back()->with('success', $dueOn ? 'Due date set.' : 'Due date cleared.');
    }

    public function waive(DealConditionWaiveRequest $request, Transaction $transaction, DealCondition $condition, UpdateDealCondition $update): RedirectResponse
    {
        abort_unless($condition->transaction_id === $transaction->id, 404);

        $update->waive($condition, $request->validated('reason'));

        return back()->with('success', 'Marked not applicable.');
    }

    public function destroy(Request $request, Transaction $transaction, DealCondition $condition, UpdateDealCondition $update): RedirectResponse
    {
        $this->authorize('manageDocuments', $transaction);
        abort_unless($condition->transaction_id === $transaction->id, 404);

        $update->remove($condition);

        return back()->with('success', "{$condition->stage->short()} item removed.");
    }
}
