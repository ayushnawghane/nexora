<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SyncTransactionContacts
{
    /**
     * Replaces the transaction's contacts with the given list.
     *
     * @param  list<array{company_contact_id: int, recipient: string}>  $contacts
     */
    public function handle(Transaction $transaction, array $contacts, User $actor): void
    {
        DB::transaction(function () use ($transaction, $contacts, $actor) {
            $transaction->contacts()->delete();
            $transaction->contacts()->createMany(array_map(fn (array $c) => [
                'company_contact_id' => $c['company_contact_id'],
                'recipient' => $c['recipient'],
            ], $contacts));
            $transaction->update(['updated_by' => $actor->id]);
        });
    }
}
