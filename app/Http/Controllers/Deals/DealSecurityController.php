<?php

namespace App\Http\Controllers\Deals;

use App\Actions\Security\ManageDiligence;
use App\Actions\Security\RecordRegistrationEvent;
use App\Actions\Security\RegisterSecurities;
use App\Actions\Security\SaveDealSecurity;
use App\Enums\ConditionStatus;
use App\Enums\DiligenceKind;
use App\Enums\RegistrationAction;
use App\Enums\RegistrationKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deals\Security\DealSecurityRequest;
use App\Http\Requests\Deals\Security\DiligenceCheckRequest;
use App\Http\Requests\Deals\Security\DiligenceFilesRequest;
use App\Http\Requests\Deals\Security\DiligenceItemRequest;
use App\Http\Requests\Deals\Security\RegistrationRequest;
use App\Models\DealDiligenceItem;
use App\Models\DealSecurity;
use App\Models\SecurityRegistration;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/** A deal's securities, their registrations (ROC, CERSAI, pledge) and its due diligence. */
class DealSecurityController extends Controller
{
    public function store(DealSecurityRequest $request, Transaction $transaction, SaveDealSecurity $save): RedirectResponse
    {
        $save->handle($transaction, null, $request->validated(), $request->user());

        return back()->with('success', 'Security added.');
    }

    public function update(DealSecurityRequest $request, Transaction $transaction, DealSecurity $security, SaveDealSecurity $save): RedirectResponse
    {
        abort_unless($security->transaction_id === $transaction->id, 404);
        $save->handle($transaction, $security, $request->validated(), $request->user());

        return back()->with('success', 'Security saved.');
    }

    public function destroy(Request $request, Transaction $transaction, DealSecurity $security, SaveDealSecurity $save): RedirectResponse
    {
        $this->authorize('manageSecurity', $transaction);
        abort_unless($security->transaction_id === $transaction->id, 404);
        $save->remove($security);

        return back()->with('success', 'Security removed.');
    }

    public function register(RegistrationRequest $request, Transaction $transaction, RegisterSecurities $register): RedirectResponse
    {
        $kind = RegistrationKind::from($request->validated('kind'));
        $register->handle(
            $transaction,
            $kind,
            array_map('intval', $request->validated('security_ids')),
            CarbonImmutable::parse($request->validated('happened_on')),
            $request->validated('filing_reference'),
            Arr::only($request->validated(), ['reference', 'amount', 'security_name', 'quantity', 'face_value', 'depository', 'pledgor_dp_id', 'pledgor_client_id', 'pledgee_dp_id', 'pledgee_client_id']),
            array_values($request->file('files', [])),
            $request->user(),
        );

        return back()->with('success', "{$kind->label()} recorded.");
    }

    public function event(RegistrationRequest $request, Transaction $transaction, SecurityRegistration $registration, RecordRegistrationEvent $record): RedirectResponse
    {
        abort_unless($registration->transaction_id === $transaction->id, 404);
        $action = RegistrationAction::from($request->validated('action'));
        $record->handle(
            $registration,
            $action,
            CarbonImmutable::parse($request->validated('happened_on')),
            $request->validated('filing_reference'),
            $request->validated('amount') !== null ? (string) $request->validated('amount') : null,
            $request->validated('reason'),
            array_values($request->file('files', [])),
            $request->user(),
        );

        return back()->with('success', "{$registration->kind->label()} {$action->label($registration->kind)}.");
    }

    public function addDiligence(DiligenceItemRequest $request, Transaction $transaction, ManageDiligence $diligence): RedirectResponse
    {
        $diligence->add($transaction, DiligenceKind::from($request->validated('kind')), $request->validated(), $request->user());

        return back()->with('success', 'Due diligence item added.');
    }

    public function uploadDiligence(DiligenceFilesRequest $request, Transaction $transaction, DealDiligenceItem $item, ManageDiligence $diligence): RedirectResponse
    {
        abort_unless($item->transaction_id === $transaction->id, 404);
        $diligence->upload($item, array_values($request->file('files')), $request->user());

        return back()->with('success', 'Files uploaded and sent for checking.');
    }

    public function checkDiligence(DiligenceCheckRequest $request, Transaction $transaction, DealDiligenceItem $item, ManageDiligence $diligence): RedirectResponse
    {
        abort_unless($item->transaction_id === $transaction->id, 404);
        $result = $diligence->check($item, $request->user(), ConditionStatus::from($request->validated('decision')), $request->validated('comment'));

        return back()->with('success', $result->status === ConditionStatus::Verified ? 'Item verified.' : 'Item sent back.');
    }

    public function removeDiligence(Request $request, Transaction $transaction, DealDiligenceItem $item, ManageDiligence $diligence): RedirectResponse
    {
        $this->authorize('manageSecurity', $transaction);
        abort_unless($item->transaction_id === $transaction->id, 404);
        $diligence->remove($item);

        return back()->with('success', 'Due diligence item removed.');
    }
}
