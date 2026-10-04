<?php

namespace App\Actions\Documents;

use App\Models\DealDocument;
use App\Models\DocumentFile;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class UploadDealDocumentFile
{
    use StoresDocumentFiles;

    /**
     * Uploads the execution version of a deal document. A newer upload replaces the current file;
     * the replaced one stays in the document's file history.
     */
    public function handle(DealDocument $document, UploadedFile $file, User $actor): DocumentFile
    {
        return $this->withFiles(function () use ($document, $file, $actor) {
            $deal = Transaction::query()->whereKey($document->transaction_id)->lockForUpdate()->firstOrFail();
            $locked = DealDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            if (! $deal->isOpenDeal()) {
                throw ValidationException::withMessages(['file' => 'The documents of a closed deal can\'t be changed.']);
            }

            $locked->files()->whereNull('removed_at')->update(['removed_at' => now(), 'removed_by' => $actor->id]);

            return $this->attach($locked, $deal, 'documents', $file, $actor);
        });
    }
}
