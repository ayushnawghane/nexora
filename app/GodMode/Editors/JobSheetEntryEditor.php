<?php

namespace App\GodMode\Editors;

use App\GodMode\Editor;
use App\Models\DealJobSheetEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

/**
 * Corrects what was recorded on a job sheet entry. Who made and checked it, and its status, stay as
 * they happened.
 *
 * @extends Editor<DealJobSheetEntry>
 */
class JobSheetEntryEditor extends Editor
{
    public function key(): string
    {
        return 'job-sheet-entry';
    }

    public function label(): string
    {
        return 'Job sheet entry';
    }

    public function modelClass(): string
    {
        return DealJobSheetEntry::class;
    }

    public function values(Model $record): array
    {
        return [
            'received_on' => $record->received_on->toDateString(),
            'maker_comment' => $record->maker_comment,
            'checker_comment' => $record->checker_comment,
        ];
    }

    public function fields(Model $record): array
    {
        return [
            ['name' => 'received_on', 'label' => 'Received on', 'type' => 'date', 'required' => true],
            ['name' => 'maker_comment', 'label' => 'Maker comment', 'type' => 'textarea'],
            ['name' => 'checker_comment', 'label' => 'Checker comment', 'type' => 'textarea'],
        ];
    }

    /**
     * The same limits the job sheet forms apply.
     */
    public function validate(Model $record, array $input, User $actor): array
    {
        $trim = fn ($v) => is_string($v) ? (trim($v) ?: null) : $v;
        $input = array_map($trim, $input);

        return Validator::make($input, [
            'received_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'maker_comment' => ['nullable', 'string', 'max:2000'],
            'checker_comment' => ['nullable', 'string', 'max:2000'],
        ], ['received_on.before_or_equal' => 'The received date can\'t be in the future.'])->validate();
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        $record->update([
            'received_on' => $validated['received_on'],
            'maker_comment' => $validated['maker_comment'] ?? null,
            'checker_comment' => $validated['checker_comment'] ?? null,
        ]);
    }

    public function transactionId(Model $record): int
    {
        return $record->transaction_id;
    }
}
