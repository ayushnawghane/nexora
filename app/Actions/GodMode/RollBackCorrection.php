<?php

namespace App\Actions\GodMode;

use App\GodMode\Editors;
use App\Models\GodModeChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RollBackCorrection
{
    /**
     * Undoes a correction by re-applying its old values through the same editor (so they must still
     * pass today's rules). Refused if the record has changed since the correction, so later edits are
     * never overwritten silently. The undo is itself a new, permanent change pointing at the original.
     */
    public function handle(GodModeChange $change, string $reason, User $actor): GodModeChange
    {
        return DB::transaction(function () use ($change, $reason, $actor) {
            /** @var GodModeChange $original */
            $original = GodModeChange::query()->whereKey($change->id)->lockForUpdate()->firstOrFail();

            if (! $original->can_roll_back) {
                throw ValidationException::withMessages(['rollback' => 'This change can\'t be undone automatically. Make a new correction instead.']);
            }
            if ($original->reverts_change_id !== null) {
                throw ValidationException::withMessages(['rollback' => 'This change is itself an undo. Make a new correction instead.']);
            }
            if ($original->revertedBy()->exists()) {
                throw ValidationException::withMessages(['rollback' => 'This change has already been undone.']);
            }

            $editor = Editors::get($original->editor);
            /** @var class-string<Model> $type */
            $type = $original->subject_type;
            /** @var Model $record */
            $record = $type::query()->whereKey($original->subject_id)->lockForUpdate()->firstOrFail();

            $current = $editor->values($record);
            if (! MakeCorrection::same($current, $original->after)) {
                throw ValidationException::withMessages(['rollback' => 'The record has changed since this correction, so undoing it would overwrite later edits. Correct it by hand instead.']);
            }

            $validated = $editor->validate($record, $original->before, $actor);
            $editor->apply($record, $validated, $actor);

            return GodModeChange::query()->create([
                'user_id' => $actor->id,
                'editor' => $original->editor,
                'subject_type' => $original->subject_type,
                'subject_id' => $original->subject_id,
                'transaction_id' => $original->transaction_id,
                'company_id' => $original->company_id,
                'reason' => trim($reason),
                'before' => $current,
                'after' => $editor->values($record->refresh()),
                'can_roll_back' => false,
                'reverts_change_id' => $original->id,
            ]);
        });
    }
}
