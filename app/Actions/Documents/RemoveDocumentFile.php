<?php

namespace App\Actions\Documents;

use App\Enums\ConditionStatus;
use App\Enums\ExecutionStatus;
use App\Models\DealCondition;
use App\Models\DealDocument;
use App\Models\DealExecution;
use App\Models\DocumentFile;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveDocumentFile
{
    /**
     * Removes a file from a deal document, a CP/CS item or an execution. The file is kept and marked
     * removed. A verified or not-applicable CP/CS item can't lose files; one waiting for a check with
     * no files left goes back to pending (a sent-back item stays sent back, with the checker's
     * comment). The same goes for an executed copy: removing it from an execution waiting for a check
     * puts the execution back to scheduled; a verified execution keeps its copy. A document in
     * execution keeps its execution version.
     */
    public function handle(DocumentFile $file, User $actor): void
    {
        $ownerClass = $file->attachable_type;
        if (! in_array($ownerClass, [DealDocument::class, DealCondition::class, DealExecution::class], true)) {
            throw ValidationException::withMessages(['file' => 'This file can\'t be removed here.']);
        }

        DB::transaction(function () use ($file, $ownerClass, $actor) {
            // Locks in the same order as every other document action: deal, item, file.
            $dealId = $ownerClass::query()->whereKey($file->attachable_id)->value('transaction_id');
            $deal = Transaction::query()->whereKey($dealId)->lockForUpdate()->first();
            /** @var DealDocument|DealCondition|DealExecution|null $owner */
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
            if ($owner instanceof DealDocument && $owner->execution()->exists()) {
                throw ValidationException::withMessages(['file' => 'This document is in execution, so its execution version stays.']);
            }
            if ($owner instanceof DealExecution && ! in_array($owner->status, [ExecutionStatus::Executed, ExecutionStatus::Returned], true)) {
                throw ValidationException::withMessages(['file' => 'The executed copy of a verified document can\'t be removed.']);
            }

            $locked->update(['removed_at' => now(), 'removed_by' => $actor->id]);

            if ($owner instanceof DealCondition && $owner->status === ConditionStatus::Submitted && $owner->currentFiles()->doesntExist()) {
                $owner->update(['status' => ConditionStatus::Pending, 'submitted_by' => null, 'submitted_at' => null]);
            }
            if ($owner instanceof DealExecution && $owner->status === ExecutionStatus::Executed) {
                $owner->update(['status' => ExecutionStatus::Scheduled, 'uploaded_by' => null, 'uploaded_at' => null]);
            }
        });
    }
}
