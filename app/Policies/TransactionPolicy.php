<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('transactions.view');
    }

    public function view(User $actor, Transaction $transaction): bool
    {
        return $actor->can('transactions.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('transactions.create');
    }

    /** Only drafts are edited through the wizard; anything later goes through approval or God Mode. */
    public function update(User $actor, Transaction $transaction): bool
    {
        return $actor->can('transactions.create') && $transaction->status->isEditable();
    }

    public function submit(User $actor, Transaction $transaction): bool
    {
        return $actor->can('transactions.submit') && $transaction->status->isEditable();
    }
}
