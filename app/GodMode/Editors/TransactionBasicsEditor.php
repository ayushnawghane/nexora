<?php

namespace App\GodMode\Editors;

use App\Actions\Transactions\SaveTransactionBasics;
use App\Enums\Origin;
use App\GodMode\Options;
use App\GodMode\TransactionEditor;
use App\Http\Requests\Transactions\TransactionBasicsRequest;
use App\Models\Arranger;
use App\Models\LeadSource;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\VerticalTeam;
use Illuminate\Database\Eloquent\Model;

/**
 * The client company can't be swapped here: the contacts, billing and letter all belong to it.
 */
class TransactionBasicsEditor extends TransactionEditor
{
    public function key(): string
    {
        return 'transaction-basics';
    }

    public function label(): string
    {
        return 'Transaction basics';
    }

    protected function request(): string
    {
        return TransactionBasicsRequest::class;
    }

    public function validate(Model $record, array $input, User $actor): array
    {
        return parent::validate($record, ['company_id' => $record->company_id] + $input, $actor);
    }

    public function values(Model $record): array
    {
        return [
            'company_id' => $record->company_id,
            'transaction_type_id' => $record->transaction_type_id,
            'lead_source_id' => $record->lead_source_id,
            'arranger_id' => $record->arranger_id,
            'vertical_team_id' => $record->vertical_team_id,
            'relationship_manager_id' => $record->relationship_manager_id,
            'signatory_id' => $record->signatory_id,
            'origin' => $record->origin->value,
            'brief' => $record->brief,
        ];
    }

    public function fields(Model $record): array
    {
        $users = fn ($query, array $current) => Options::records($query, 'name', $current);

        return [
            ['name' => 'vertical_team_id', 'label' => 'Vertical team', 'type' => 'select', 'required' => true, 'numeric' => true,
                'options' => Options::records(VerticalTeam::query(), 'name', [$record->vertical_team_id])],
            ['name' => 'relationship_manager_id', 'label' => 'Relationship manager', 'type' => 'select', 'required' => true, 'numeric' => true,
                'options' => $users(User::query(), [$record->relationship_manager_id])],
            ['name' => 'signatory_id', 'label' => 'Signatory', 'type' => 'select', 'numeric' => true,
                'options' => $users(User::query()->where('is_authorised_signatory', true), [$record->signatory_id])],
            ['name' => 'transaction_type_id', 'label' => 'Transaction type', 'type' => 'select', 'numeric' => true,
                'options' => Options::records(TransactionType::query(), 'name', [$record->transaction_type_id])],
            ['name' => 'lead_source_id', 'label' => 'Lead source', 'type' => 'select', 'numeric' => true,
                'options' => Options::records(LeadSource::query(), 'name', [$record->lead_source_id])],
            ['name' => 'arranger_id', 'label' => 'Arranger', 'type' => 'select', 'numeric' => true,
                'options' => Options::records(Arranger::query(), 'name', [$record->arranger_id])],
            ['name' => 'origin', 'label' => 'Origin', 'type' => 'select', 'required' => true, 'options' => Options::enum(Origin::class)],
            ['name' => 'brief', 'label' => 'Brief', 'type' => 'textarea'],
        ];
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        app(SaveTransactionBasics::class)->handle($validated, $actor, $record);
    }
}
