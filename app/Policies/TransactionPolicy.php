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

    /** The deal workspace exists once the engagement letter has been issued. */
    public function viewDeal(User $actor, Transaction $transaction): bool
    {
        return $actor->can('deals.view') && $transaction->isDeal();
    }

    public function editDeal(User $actor, Transaction $transaction): bool
    {
        return $actor->can('deals.edit') && $transaction->isOpenDeal();
    }

    public function requestStatus(User $actor, Transaction $transaction): bool
    {
        return $actor->can('deals.status.request') && $transaction->isOpenDeal();
    }

    public function makeJobSheet(User $actor, Transaction $transaction): bool
    {
        return $actor->can('deals.jobsheet.make') && $transaction->isOpenDeal();
    }

    public function checkJobSheet(User $actor, Transaction $transaction): bool
    {
        return $actor->can('deals.jobsheet.check') && $transaction->isOpenDeal();
    }

    public function manageDocuments(User $actor, Transaction $transaction): bool
    {
        return $actor->can('deals.documents.manage') && $transaction->isOpenDeal();
    }

    public function verifyDocuments(User $actor, Transaction $transaction): bool
    {
        return $actor->can('deals.documents.verify') && $transaction->isOpenDeal();
    }
}
