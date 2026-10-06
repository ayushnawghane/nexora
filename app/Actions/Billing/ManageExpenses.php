<?php

namespace App\Actions\Billing;

use App\Actions\Documents\StoresDocumentFiles;
use App\Models\DealExpense;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * A deal's out-of-pocket expenses, with their proof. Billed expenses are locked until the invoice
 * billing them is cancelled.
 */
class ManageExpenses
{
    use StoresDocumentFiles;

    /**
     * @param  array{incurred_on?: string|null, description: string, amount: string}  $data
     * @param  list<UploadedFile>  $files
     */
    public function add(Transaction $deal, array $data, array $files, User $actor): DealExpense
    {
        return $this->withFiles(function () use ($deal, $data, $files, $actor) {
            /** @var Transaction $locked */
            $locked = Transaction::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isDeal()) {
                throw ValidationException::withMessages(['description' => 'Expenses are recorded on deals.']);
            }
            $expense = $locked->expenses()->create([...$data, 'created_by' => $actor->id]);
            foreach ($files as $file) {
                $this->attach($expense, $locked, 'expenses', $file, $actor);
            }

            return $expense;
        });
    }

    /**
     * @param  array{incurred_on?: string|null, description: string, amount: string}  $data
     * @param  list<UploadedFile>  $files  added to the proof already there
     */
    public function update(DealExpense $expense, array $data, array $files, User $actor): DealExpense
    {
        return $this->withFiles(function () use ($expense, $data, $files, $actor) {
            $locked = $this->lockUnbilled($expense);
            $locked->update($data);
            foreach ($files as $file) {
                $this->attach($locked, $locked->transaction, 'expenses', $file, $actor);
            }

            return $locked;
        });
    }

    public function remove(DealExpense $expense, User $actor): void
    {
        $this->withFiles(function () use ($expense, $actor) {
            $this->lockUnbilled($expense)->update(['removed_at' => now(), 'removed_by' => $actor->id]);
        });
    }

    private function lockUnbilled(DealExpense $expense): DealExpense
    {
        /** @var DealExpense $locked */
        $locked = DealExpense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();
        if ($locked->removed_at !== null) {
            throw ValidationException::withMessages(['description' => 'This expense was removed.']);
        }
        if ($locked->invoice_id !== null) {
            throw ValidationException::withMessages(['description' => 'This expense is on an invoice. Cancel or discard that invoice to change it.']);
        }

        return $locked;
    }
}
