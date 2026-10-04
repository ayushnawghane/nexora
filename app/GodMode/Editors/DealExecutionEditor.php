<?php

namespace App\GodMode\Editors;

use App\GodMode\Editor;
use App\Models\DealExecution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

/**
 * Corrects what was recorded about an execution: the place, the dates and the comments. Its
 * status, signatory, executed copy and who uploaded or checked it stay as they happened.
 *
 * @extends Editor<DealExecution>
 */
class DealExecutionEditor extends Editor
{
    public function key(): string
    {
        return 'deal-execution';
    }

    public function label(): string
    {
        return 'Execution';
    }

    public function modelClass(): string
    {
        return DealExecution::class;
    }

    public function values(Model $record): array
    {
        return [
            'place' => $record->place,
            'executed_on' => $record->executed_on?->toDateString(),
            'document_date' => $record->document_date?->toDateString(),
            'comments' => $record->comments,
            'checker_comment' => $record->checker_comment,
        ];
    }

    public function fields(Model $record): array
    {
        return [
            ['name' => 'place', 'label' => 'Place', 'type' => 'text'],
            ['name' => 'executed_on', 'label' => 'Executed on', 'type' => 'date'],
            ['name' => 'document_date', 'label' => 'Document date', 'type' => 'date'],
            ['name' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
            ['name' => 'checker_comment', 'label' => 'Checker comment', 'type' => 'textarea'],
        ];
    }

    /**
     * The limits the execution forms apply.
     */
    public function validate(Model $record, array $input, User $actor): array
    {
        $input = array_map(fn ($v) => is_string($v) ? (trim($v) ?: null) : $v, $input);

        return Validator::make($input, [
            'place' => [$record->scheduled_at ? 'required' : 'nullable', 'string', 'max:100'],
            'executed_on' => [$record->uploaded_at ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'document_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:executed_on'],
            'comments' => ['nullable', 'string', 'max:1000'],
            'checker_comment' => ['nullable', 'string', 'max:2000'],
        ], [
            'executed_on.before_or_equal' => 'The execution date can\'t be in the future.',
            'document_date.before_or_equal' => 'The document date can\'t be after the execution date.',
        ])->validate();
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        $record->update([
            'place' => $validated['place'] ?? null,
            'executed_on' => $validated['executed_on'] ?? null,
            'document_date' => $validated['document_date'] ?? null,
            'comments' => $validated['comments'] ?? null,
            'checker_comment' => $validated['checker_comment'] ?? null,
        ]);
    }

    public function transactionId(Model $record): int
    {
        return $record->transaction_id;
    }
}
