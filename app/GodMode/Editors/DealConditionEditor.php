<?php

namespace App\GodMode\Editors;

use App\GodMode\Editor;
use App\GodMode\Options;
use App\Models\DealCondition;
use App\Models\IssuingAuthority;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Corrects what was recorded on a CP/CS item. Its status, files and who submitted or checked it
 * stay as they happened.
 *
 * @extends Editor<DealCondition>
 */
class DealConditionEditor extends Editor
{
    public function key(): string
    {
        return 'deal-condition';
    }

    public function label(): string
    {
        return 'CP / CS item';
    }

    public function modelClass(): string
    {
        return DealCondition::class;
    }

    public function values(Model $record): array
    {
        return [
            'name' => $record->name,
            'issuing_authority_id' => $record->issuing_authority_id,
            'due_on' => $record->due_on?->toDateString(),
            'checker_comment' => $record->checker_comment,
            'waived_reason' => $record->waived_reason,
        ];
    }

    public function fields(Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Document', 'type' => 'textarea', 'required' => true],
            ['name' => 'issuing_authority_id', 'label' => 'Issuing authority', 'type' => 'select', 'numeric' => true,
                'options' => Options::records(IssuingAuthority::query(), 'name', [$record->issuing_authority_id])],
            ['name' => 'due_on', 'label' => 'Due date', 'type' => 'date'],
            ['name' => 'checker_comment', 'label' => 'Checker comment', 'type' => 'textarea'],
            ['name' => 'waived_reason', 'label' => 'Not applicable because', 'type' => 'textarea'],
        ];
    }

    /**
     * The limits the CP/CS forms apply. The name stays unique among the deal's items of the same stage.
     */
    public function validate(Model $record, array $input, User $actor): array
    {
        $input = array_map(fn ($v) => is_string($v) ? (trim($v) ?: null) : $v, $input);

        return Validator::make($input, [
            'name' => ['required', 'string', 'max:2000', Rule::unique('deal_conditions', 'name')
                ->where('transaction_id', $record->transaction_id)->where('stage', $record->stage->value)->ignore($record->id)],
            'issuing_authority_id' => ['nullable', 'integer', Rule::exists('issuing_authorities', 'id')],
            'due_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'checker_comment' => ['nullable', 'string', 'max:2000'],
            'waived_reason' => ['nullable', 'string', 'max:2000'],
        ], ['name.unique' => 'The deal already has an item with this name.'])->validate();
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        $record->update([
            'name' => $validated['name'],
            'issuing_authority_id' => $validated['issuing_authority_id'] ?? null,
            'due_on' => $validated['due_on'] ?? null,
            'checker_comment' => $validated['checker_comment'] ?? null,
            'waived_reason' => $validated['waived_reason'] ?? null,
        ]);
    }

    public function transactionId(Model $record): int
    {
        return $record->transaction_id;
    }
}
