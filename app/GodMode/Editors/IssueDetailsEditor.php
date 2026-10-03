<?php

namespace App\GodMode\Editors;

use App\Actions\Transactions\SaveIssueDetails;
use App\Enums\Instrument;
use App\Enums\IssueType;
use App\Enums\Listing;
use App\GodMode\Options;
use App\GodMode\TransactionEditor;
use App\Http\Requests\Transactions\TransactionIssueRequest;
use App\Models\TransactionInstrument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Saving rebuilds the fee schedule (as in the wizard), which then has to be verified again.
 */
class IssueDetailsEditor extends TransactionEditor
{
    public function key(): string
    {
        return 'issue-details';
    }

    public function label(): string
    {
        return 'Issue details';
    }

    protected function request(): string
    {
        return TransactionIssueRequest::class;
    }

    public function values(Model $record): array
    {
        $issue = $record->issueDetail()->first();

        return [
            'listing' => $issue?->listing->value,
            'issue_type' => $issue?->issue_type->value,
            'is_secured' => (bool) $issue?->is_secured,
            'is_rated' => (bool) $issue?->is_rated,
            'base_issue_size' => $issue?->base_issue_size,
            'green_shoe_size' => $issue?->green_shoe_size,
            'tenure_months' => $issue?->tenure_months,
            'tenure_days' => $issue?->tenure_days,
            'instruments' => $record->instruments()->orderBy('id')->get()->map(fn (TransactionInstrument $i) => [
                'instrument' => $i->instrument->value,
                'base_amount' => $i->base_amount,
                'green_shoe_amount' => $i->green_shoe_amount,
            ])->all(),
        ];
    }

    public function fields(Model $record): array
    {
        return [
            ['name' => 'listing', 'label' => 'Listing', 'type' => 'select', 'required' => true, 'options' => Options::enum(Listing::class)],
            ['name' => 'issue_type', 'label' => 'Issue type', 'type' => 'select', 'required' => true, 'options' => Options::enum(IssueType::class)],
            ['name' => 'is_secured', 'label' => 'Secured', 'type' => 'boolean'],
            ['name' => 'is_rated', 'label' => 'Rated', 'type' => 'boolean'],
            ['name' => 'base_issue_size', 'label' => 'Base issue size (₹)', 'type' => 'number', 'required' => true],
            ['name' => 'green_shoe_size', 'label' => 'Green shoe (₹)', 'type' => 'number', 'required' => true],
            ['name' => 'tenure_months', 'label' => 'Tenure (months)', 'type' => 'number', 'required' => true],
            ['name' => 'tenure_days', 'label' => 'Tenure (extra days)', 'type' => 'number', 'required' => true],
            [
                'name' => 'instruments', 'label' => 'Instrument split', 'type' => 'rows', 'addLabel' => 'Add instrument',
                'hint' => 'Must add up exactly to the base issue and the green shoe.',
                'blank' => ['instrument' => null, 'base_amount' => '0', 'green_shoe_amount' => '0'],
                'fields' => [
                    ['name' => 'instrument', 'label' => 'Instrument', 'type' => 'select', 'required' => true, 'options' => Options::enum(Instrument::class)],
                    ['name' => 'base_amount', 'label' => 'Base (₹)', 'type' => 'number', 'required' => true],
                    ['name' => 'green_shoe_amount', 'label' => 'Green shoe (₹)', 'type' => 'number', 'required' => true],
                ],
            ],
        ];
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        app(SaveIssueDetails::class)->handle($record, $validated, $actor);
    }

    /** Undoing needs the old issue details to exist (a first save can't be undone). */
    public function canRollBack(array $before): bool
    {
        return $before['listing'] !== null;
    }
}
