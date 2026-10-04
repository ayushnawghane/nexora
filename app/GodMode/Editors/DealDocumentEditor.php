<?php

namespace App\GodMode\Editors;

use App\GodMode\Editor;
use App\Models\DealDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

/**
 * Corrects the name a legal document carries on a deal (e.g. a supplement numbered wrongly in
 * Stack). Its type, kind and files stay as they are.
 *
 * @extends Editor<DealDocument>
 */
class DealDocumentEditor extends Editor
{
    public function key(): string
    {
        return 'deal-document';
    }

    public function label(): string
    {
        return 'Legal document';
    }

    public function modelClass(): string
    {
        return DealDocument::class;
    }

    public function values(Model $record): array
    {
        return ['name' => $record->name];
    }

    public function fields(Model $record): array
    {
        return [['name' => 'name', 'label' => 'Name on the deal', 'type' => 'text', 'required' => true]];
    }

    public function validate(Model $record, array $input, User $actor): array
    {
        $input = array_map(fn ($v) => is_string($v) ? (trim($v) ?: null) : $v, $input);

        return Validator::make($input, ['name' => ['required', 'string', 'max:255']])->validate();
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        $record->update(['name' => $validated['name']]);
    }

    public function transactionId(Model $record): int
    {
        return $record->transaction_id;
    }
}
