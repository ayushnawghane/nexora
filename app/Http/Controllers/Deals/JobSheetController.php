<?php

namespace App\Http\Controllers\Deals;

use App\Actions\Deals\CheckJobSheetEntry;
use App\Actions\Deals\SubmitJobSheetEntry;
use App\Enums\JobSheetStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deals\JobSheetCheckRequest;
use App\Http\Requests\Deals\JobSheetSubmitRequest;
use App\Models\DealJobSheetEntry;
use App\Models\JobSheetActivity;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

class JobSheetController extends Controller
{
    public function submit(JobSheetSubmitRequest $request, Transaction $transaction, JobSheetActivity $activity, SubmitJobSheetEntry $submit): RedirectResponse
    {
        $submit->handle($transaction, $activity, $request->user(), CarbonImmutable::parse($request->validated('received_on')), $request->validated('comment'));

        return back()->with('success', "{$activity->name} sent for checking.");
    }

    public function check(JobSheetCheckRequest $request, Transaction $transaction, DealJobSheetEntry $entry, CheckJobSheetEntry $check): RedirectResponse
    {
        abort_unless($entry->transaction_id === $transaction->id, 404);

        $result = $check->handle($entry, $request->user(), JobSheetStatus::from($request->validated('decision')), $request->validated('comment'));

        return back()->with('success', $result->status === JobSheetStatus::Verified ? 'Entry verified.' : 'Entry sent back to the maker.');
    }
}
