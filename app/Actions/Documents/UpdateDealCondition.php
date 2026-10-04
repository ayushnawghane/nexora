<?php

namespace App\Actions\Documents;

use App\Enums\ConditionStatus;
use App\Models\DealCondition;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The smaller changes to a CP/CS item: its due date, marking it not applicable, removing it. */
class UpdateDealCondition
{
    public function setDueDate(DealCondition $condition, ?CarbonImmutable $dueOn): DealCondition
    {
        return $this->locked($condition, 'due_on', function (DealCondition $locked) use ($dueOn) {
            if (! $locked->status->isOpen()) {
                throw ValidationException::withMessages(['due_on' => "This item is marked {$locked->status->label()}; its due date can't change."]);
            }
            $locked->update(['due_on' => $dueOn]);

            return $locked;
        });
    }

    /**
     * Marks an item as not applicable to this deal, with the reason. Its files stay on record.
     */
    public function waive(DealCondition $condition, string $reason): DealCondition
    {
        return $this->locked($condition, 'reason', function (DealCondition $locked) use ($reason) {
            if (! $locked->status->isOpen()) {
                throw ValidationException::withMessages(['reason' => "This item is already marked {$locked->status->label()}."]);
            }
            $locked->update(['status' => ConditionStatus::Waived, 'waived_reason' => $reason]);

            return $locked;
        });
    }

    /**
     * Removes an item added by mistake. Only one that never had a file uploaded can be removed;
     * anything else is marked not applicable instead, so its history stays.
     */
    public function remove(DealCondition $condition): void
    {
        $this->locked($condition, 'condition', function (DealCondition $locked) {
            if ($locked->status !== ConditionStatus::Pending || $locked->files()->exists()) {
                throw ValidationException::withMessages(['condition' => 'Files have been uploaded to this item, so it can\'t be removed. Mark it not applicable instead.']);
            }
            $locked->delete();

            return $locked;
        });
    }

    /**
     * @param  callable(DealCondition): DealCondition  $change
     */
    private function locked(DealCondition $condition, string $field, callable $change): DealCondition
    {
        return DB::transaction(function () use ($condition, $field, $change) {
            $deal = Transaction::query()->whereKey($condition->transaction_id)->lockForUpdate()->firstOrFail();
            $locked = DealCondition::query()->whereKey($condition->id)->lockForUpdate()->firstOrFail();

            if (! $deal->isOpenDeal()) {
                throw ValidationException::withMessages([$field => 'The documents of a closed deal can\'t be changed.']);
            }

            return $change($locked);
        });
    }
}
