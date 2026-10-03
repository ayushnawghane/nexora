<?php

namespace App\Actions\Deals;

use App\Enums\StatusRequestState;
use App\Models\DealStatusRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawDealStatusRequest
{
    /** The person who raised an open request can take it back before it is decided. */
    public function handle(DealStatusRequest $request, User $actor): void
    {
        DB::transaction(function () use ($request, $actor) {
            $locked = DealStatusRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['request' => 'This request has already been decided.']);
            }
            if ($locked->requested_by !== $actor->id) {
                throw ValidationException::withMessages(['request' => 'Only the person who raised the request can withdraw it.']);
            }

            $locked->update(['status' => StatusRequestState::Withdrawn, 'closed_at' => now()]);
        });
    }
}
