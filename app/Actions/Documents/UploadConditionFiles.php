<?php

namespace App\Actions\Documents;

use App\Enums\ConditionStatus;
use App\Models\DealCondition;
use App\Models\DocumentFile;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class UploadConditionFiles
{
    use StoresDocumentFiles;

    /**
     * Uploads files to a CP/CS item and sends it for checking. The uploader becomes the maker, so a
     * different person has to check it. A sent-back item can be fixed and sent again.
     *
     * @param  list<UploadedFile>  $files
     */
    public function handle(DealCondition $condition, array $files, User $actor): DealCondition
    {
        if ($files === []) {
            throw ValidationException::withMessages(['files' => 'Choose at least one file.']);
        }

        return $this->withFiles(function () use ($condition, $files, $actor) {
            $deal = Transaction::query()->whereKey($condition->transaction_id)->lockForUpdate()->firstOrFail();
            $locked = DealCondition::query()->whereKey($condition->id)->lockForUpdate()->firstOrFail();

            if (! $deal->isOpenDeal()) {
                throw ValidationException::withMessages(['files' => 'The documents of a closed deal can\'t be changed.']);
            }
            if (! $locked->status->isOpen()) {
                throw ValidationException::withMessages(['files' => "This item is marked {$locked->status->label()}; no more files can be added."]);
            }
            if ($locked->currentFiles()->count() + count($files) > DocumentFile::MAX_PER_ITEM) {
                throw ValidationException::withMessages(['files' => 'An item can hold at most '.DocumentFile::MAX_PER_ITEM.' files. Remove some first.']);
            }

            foreach ($files as $file) {
                $this->attach($locked, $deal, 'conditions', $file, $actor);
            }

            $locked->update([
                'status' => ConditionStatus::Submitted,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'checker_id' => null,
                'checked_at' => null,
            ]);

            return $locked;
        });
    }
}
