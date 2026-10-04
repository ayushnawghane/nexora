<?php

namespace App\Actions\Documents;

use App\Enums\ConditionStatus;
use App\Models\DealCondition;
use App\Models\DealDocument;
use App\Models\DocumentFile;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveDocumentFile
{
    /**
     * Removes a file from a deal document or a CP/CS item. The file is kept and marked removed.
     * A verified or not-applicable CP/CS item can't lose files; one waiting for a check with no files
     * left goes back to pending (a sent-back item stays sent back, with the checker's comment).
     */
    public function handle(DocumentFile $file, User $actor): void
    {
        $ownerClass = $file->attachable_type;
        if (! in_array($ownerClass, [DealDocument::class, DealCondition::class], true)) {
            throw ValidationException::withMessages(['file' => 'This file can\'t be removed here.']);
        }

        DB::transaction(function () use ($file, $ownerClass, $actor) {
            // Locks in the same order as every other document action: deal, item, file.
            $dealId = $ownerClass::query()->whereKey($file->attachable_id)->value('transaction_id');
            $deal = Transaction::query()->whereKey($dealId)->lockForUpdate()->first();
            /** @var DealDocument|DealCondition|null $owner */
            $owner = $ownerClass::query()->whereKey($file->attachable_id)->lockForUpdate()->first();
            $locked = DocumentFile::query()->whereKey($file->id)->lockForUpdate()->firstOrFail();

            if ($deal === null || $owner === null) {
                throw ValidationException::withMessages(['file' => 'This file belongs to a document that has been removed.']);
            }
            if (! $deal->isOpenDeal()) {
                throw ValidationException::withMessages(['file' => 'The documents of a closed deal can\'t be changed.']);
            }
            if ($locked->removed_at !== null) {
                throw ValidationException::withMessages(['file' => 'This file has already been removed.']);
            }
            if ($owner instanceof DealCondition && ! $owner->status->isOpen()) {
                throw ValidationException::withMessages(['file' => "Files of an item marked {$owner->status->label()} can't be removed."]);
            }

            $locked->update(['removed_at' => now(), 'removed_by' => $actor->id]);

            if ($owner instanceof DealCondition && $owner->status === ConditionStatus::Submitted && $owner->currentFiles()->doesntExist()) {
                $owner->update(['status' => ConditionStatus::Pending, 'submitted_by' => null, 'submitted_at' => null]);
            }
        });
    }
}
