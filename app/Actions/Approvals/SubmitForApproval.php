<?php

namespace App\Actions\Approvals;

use App\Enums\ApprovalStatus;
use App\Enums\Recipient;
use App\Enums\TransactionStatus;
use App\Models\ApprovalRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ApprovalRequested;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SubmitForApproval
{
    /**
     * Opens an approval request for a complete draft with a verified schedule and notifies the
     * approvers (after commit). The transaction row is locked, so a double click can't open two.
     */
    public function handle(Transaction $transaction, User $actor): ApprovalRequest
    {
        $request = DB::transaction(function () use ($transaction, $actor) {
            $locked = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== TransactionStatus::Draft) {
                throw ValidationException::withMessages(['transaction' => 'This transaction has already been sent for approval.']);
            }

            $missing = $this->missing($locked);
            if ($missing !== []) {
                throw ValidationException::withMessages(['transaction' => 'Complete these steps first: '.implode(', ', $missing).'.']);
            }

            $locked->transitionTo(TransactionStatus::PendingApproval);
            $locked->submitted_at = now();
            $locked->updated_by = $actor->id;
            $locked->save();

            return $locked->approvalRequests()->create([
                'status' => ApprovalStatus::Open,
                'requested_by' => $actor->id,
            ]);
        });

        $approvers = User::query()->active()->permission('approvals.vote')->whereKeyNot($actor->id)->get();
        Notification::send($approvers, new ApprovalRequested($request));

        $transaction->refresh();

        return $request;
    }

    /**
     * @return list<string>
     */
    private function missing(Transaction $transaction): array
    {
        $missing = [];
        if (! $transaction->contacts()->where('recipient', Recipient::To)->exists()) {
            $missing[] = 'contacts';
        }
        if ($transaction->issueDetail()->doesntExist()) {
            $missing[] = 'issue details';
        }
        if ($transaction->feeLines()->doesntExist()) {
            $missing[] = 'fees';
        } elseif (! $transaction->isScheduleVerified()) {
            $missing[] = 'schedule verification';
        }

        return $missing;
    }
}
