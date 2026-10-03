<?php

namespace App\GodMode\Editors;

use App\Actions\Transactions\SyncTransactionContacts;
use App\Enums\Recipient;
use App\GodMode\Options;
use App\GodMode\TransactionEditor;
use App\Http\Requests\Transactions\TransactionContactsRequest;
use App\Models\CompanyContact;
use App\Models\TransactionContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Who the engagement letter is addressed to (To / Cc).
 */
class TransactionContactsEditor extends TransactionEditor
{
    public function key(): string
    {
        return 'transaction-contacts';
    }

    public function label(): string
    {
        return 'Letter contacts';
    }

    protected function request(): string
    {
        return TransactionContactsRequest::class;
    }

    public function values(Model $record): array
    {
        return [
            'contacts' => $record->contacts()->orderBy('id')->get()->map(fn (TransactionContact $c) => [
                'company_contact_id' => $c->company_contact_id,
                'recipient' => $c->recipient->value,
            ])->all(),
        ];
    }

    public function fields(Model $record): array
    {
        $current = $record->contacts()->pluck('company_contact_id')->all();

        return [[
            'name' => 'contacts', 'label' => 'Contacts', 'type' => 'rows', 'addLabel' => 'Add contact',
            'blank' => ['company_contact_id' => null, 'recipient' => Recipient::To->value],
            'fields' => [
                ['name' => 'company_contact_id', 'label' => 'Contact', 'type' => 'select', 'required' => true, 'numeric' => true,
                    'options' => Options::records(CompanyContact::query()->where('company_id', $record->company_id), 'name', $current)],
                ['name' => 'recipient', 'label' => 'To / Cc', 'type' => 'select', 'required' => true, 'options' => Options::enum(Recipient::class)],
            ],
        ]];
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        app(SyncTransactionContacts::class)->handle($record, $validated['contacts'], $actor);
    }
}
