<?php

namespace App\Actions\Execution;

use App\Actions\Documents\StoresDocumentFiles;
use App\Enums\ExecutionStatus;
use App\Models\DealExecution;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class RecordExecution
{
    use StoresDocumentFiles;

    /**
     * Records a scheduled document as executed: the executed copy, its dates and any comments. The
     * uploader becomes the maker, so a different person has to verify it. A sent-back execution is
     * recorded again the same way; a new copy replaces the old one, which stays in the history.
     */
    public function handle(DealExecution $execution, UploadedFile $file, ?CarbonImmutable $documentDate, CarbonImmutable $executedOn, ?string $comments, User $actor): DealExecution
    {
        return $this->withFiles(function () use ($execution, $file, $documentDate, $executedOn, $comments, $actor) {
            $deal = Transaction::query()->whereKey($execution->transaction_id)->lockForUpdate()->firstOrFail();
            $locked = DealExecution::query()->whereKey($execution->id)->lockForUpdate()->firstOrFail();

            if (! $deal->isOpenDeal()) {
                throw ValidationException::withMessages(['file' => 'The documents of a closed deal can\'t be changed.']);
            }
            if (! $locked->status->canRecord()) {
                throw ValidationException::withMessages(['file' => $locked->status === ExecutionStatus::ToSchedule
                    ? 'Schedule the execution first.'
                    : "This execution is {$locked->status->label()}; it can't be recorded again."]);
            }
            if ($executedOn->isAfter(today())) {
                throw ValidationException::withMessages(['executed_on' => 'The execution date can\'t be in the future.']);
            }
            if ($documentDate && $documentDate->isAfter($executedOn)) {
                throw ValidationException::withMessages(['document_date' => 'The document date can\'t be after the execution date.']);
            }

            $locked->files()->whereNull('removed_at')->update(['removed_at' => now(), 'removed_by' => $actor->id]);
            $this->attach($locked, $deal, 'executed', $file, $actor);

            $locked->update([
                'status' => ExecutionStatus::Executed,
                'document_date' => $documentDate,
                'executed_on' => $executedOn,
                'comments' => trim((string) $comments) ?: null,
                'uploaded_by' => $actor->id,
                'uploaded_at' => now(),
                'checker_id' => null,
                'checked_at' => null,
            ]);

            return $locked;
        });
    }
}
