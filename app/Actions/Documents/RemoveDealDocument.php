<?php

namespace App\Actions\Documents;

use App\Models\DealDocument;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveDealDocument
{
    /**
     * Takes a document off the deal's list. Only one without a current file can go: remove the file
     * first. The document and its file history stay in the database.
     */
    public function handle(DealDocument $document, User $actor): void
    {
        DB::transaction(function () use ($document, $actor) {
            $deal = Transaction::query()->whereKey($document->transaction_id)->lockForUpdate()->firstOrFail();
            $locked = DealDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            if (! $deal->isOpenDeal()) {
                throw ValidationException::withMessages(['document' => 'The documents of a closed deal can\'t be changed.']);
            }
            if ($locked->execution()->exists()) {
                throw ValidationException::withMessages(['document' => 'This document is in execution, so it can\'t be removed.']);
            }
            if ($locked->currentFile()->exists()) {
                throw ValidationException::withMessages(['document' => 'Remove the uploaded file before removing the document.']);
            }

            $locked->forceFill(['removed_by' => $actor->id])->save();
            $locked->delete();
        });
    }
}
