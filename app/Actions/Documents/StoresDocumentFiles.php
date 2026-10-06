<?php

namespace App\Actions\Documents;

use App\Models\DealCondition;
use App\Models\DealDiligenceItem;
use App\Models\DealDocument;
use App\Models\DealExecution;
use App\Models\DealExpense;
use App\Models\DocumentFile;
use App\Models\IsinAllotment;
use App\Models\IsinPayment;
use App\Models\SecurityRegistrationEvent;
use App\Models\Transaction;
use App\Models\User;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Stores uploads on the private disk while the database work runs, and deletes them again if that
 * work fails, so no file is left behind without a record (and no record points at a missing file).
 */
trait StoresDocumentFiles
{
    /** @var list<string> paths stored during the current call */
    private array $stored = [];

    /**
     * Runs $work in a DB transaction; files stored through attach() inside it are removed on failure.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function withFiles(Closure $work): mixed
    {
        $this->stored = [];

        try {
            return DB::transaction($work);
        } catch (Throwable $e) {
            $this->discardStored();
            throw $e;
        } finally {
            $this->stored = [];
        }
    }

    /** Deletes the files stored by the failed call. */
    private function discardStored(): void
    {
        foreach ($this->stored as $path) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Stores the file under the deal's folder and records it against $owner.
     */
    private function attach(DealDocument|DealCondition|DealExecution|DealDiligenceItem|SecurityRegistrationEvent|IsinAllotment|IsinPayment|DealExpense $owner, Transaction $deal, string $folder, UploadedFile $file, User $actor): DocumentFile
    {
        $path = $file->store("deals/{$deal->ulid}/{$folder}", 'local');
        if ($path === false) {
            throw new \RuntimeException('The file could not be stored.');
        }
        $this->stored[] = $path;

        return $owner->files()->create([
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $actor->id,
        ]);
    }
}
