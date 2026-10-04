<?php

namespace App\GodMode\Editors;

use App\GodMode\Editor;
use App\GodMode\Options;
use App\Models\DealDiligenceItem;
use App\Models\EmpanelledAgency;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Corrects what was recorded on a due diligence item. Its status, files and who submitted or
 * checked it stay as they happened.
 *
 * @extends Editor<DealDiligenceItem>
 */
class DiligenceItemEditor extends Editor
{
    public function key(): string
    {
        return 'diligence-item';
    }

    public function label(): string
    {
        return 'Due diligence item';
    }

    public function modelClass(): string
    {
        return DealDiligenceItem::class;
    }

    public function values(Model $record): array
    {
        return [
            'title' => $record->title,
            'asset_owner' => $record->asset_owner,
            'empanelled_agency_id' => $record->empanelled_agency_id,
            'reference' => $record->reference,
            'checker_comment' => $record->checker_comment,
        ];
    }

    public function fields(Model $record): array
    {
        return [
            ['name' => 'title', 'label' => 'Document', 'type' => 'text', 'required' => true],
            ['name' => 'asset_owner', 'label' => 'Asset owner searched', 'type' => 'text', 'required' => $record->kind->needsAssetOwner()],
            ['name' => 'empanelled_agency_id', 'label' => 'Issued by (empanelled agency)', 'type' => 'select', 'numeric' => true,
                'options' => Options::records(EmpanelledAgency::query(), 'name', [$record->empanelled_agency_id])],
            ['name' => 'reference', 'label' => 'UDIN / reference', 'type' => 'text'],
            ['name' => 'checker_comment', 'label' => 'Checker comment', 'type' => 'textarea'],
        ];
    }

    public function validate(Model $record, array $input, User $actor): array
    {
        $input = array_map(fn ($v) => is_string($v) ? (trim($v) ?: null) : $v, $input);

        return Validator::make($input, [
            'title' => ['required', 'string', 'max:500'],
            'asset_owner' => [$record->kind->needsAssetOwner() ? 'required' : 'nullable', 'string', 'max:255'],
            'empanelled_agency_id' => ['nullable', 'integer', Rule::exists('empanelled_agencies', 'id')],
            'reference' => ['nullable', 'string', 'max:60'],
            'checker_comment' => ['nullable', 'string', 'max:2000'],
        ])->validate();
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        $record->update([
            'title' => $validated['title'],
            'asset_owner' => $validated['asset_owner'] ?? null,
            'empanelled_agency_id' => $validated['empanelled_agency_id'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'checker_comment' => $validated['checker_comment'] ?? null,
        ]);
    }

    public function transactionId(Model $record): int
    {
        return $record->transaction_id;
    }
}
