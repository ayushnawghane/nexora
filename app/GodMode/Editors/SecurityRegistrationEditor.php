<?php

namespace App\GodMode\Editors;

use App\Enums\RegistrationKind;
use App\GodMode\Editor;
use App\Models\SecurityRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Corrects a registration's reference, amount and (pledges) depository details. Its filings
 * (events) and the securities it covers stay as recorded.
 *
 * @extends Editor<SecurityRegistration>
 */
class SecurityRegistrationEditor extends Editor
{
    public function key(): string
    {
        return 'security-registration';
    }

    public function label(): string
    {
        return 'Registration';
    }

    public function modelClass(): string
    {
        return SecurityRegistration::class;
    }

    public function values(Model $record): array
    {
        $values = ['reference' => $record->reference, 'amount' => $record->amount];
        if ($record->kind === RegistrationKind::Pledge) {
            $values += [
                'security_name' => $record->security_name,
                'quantity' => $record->quantity,
                'face_value' => $record->face_value,
                'depository' => $record->depository,
                'pledgor_dp_id' => $record->pledgor_dp_id,
                'pledgor_client_id' => $record->pledgor_client_id,
                'pledgee_dp_id' => $record->pledgee_dp_id,
                'pledgee_client_id' => $record->pledgee_client_id,
            ];
        }

        return $values;
    }

    public function fields(Model $record): array
    {
        $fields = [
            ['name' => 'reference', 'label' => $record->kind->referenceLabel(), 'type' => 'text'],
            ['name' => 'amount', 'label' => 'Amount (₹)', 'type' => 'number'],
        ];
        if ($record->kind === RegistrationKind::Pledge) {
            $fields = [...$fields,
                ['name' => 'security_name', 'label' => 'Security pledged', 'type' => 'text', 'required' => true],
                ['name' => 'quantity', 'label' => 'Number of securities', 'type' => 'number', 'required' => true],
                ['name' => 'face_value', 'label' => 'Face value (₹)', 'type' => 'number'],
                ['name' => 'depository', 'label' => 'Depository', 'type' => 'select', 'required' => true,
                    'options' => [['value' => 'NSDL', 'label' => 'NSDL'], ['value' => 'CDSL', 'label' => 'CDSL']]],
                ['name' => 'pledgor_dp_id', 'label' => 'Pledgor DP ID', 'type' => 'text'],
                ['name' => 'pledgor_client_id', 'label' => 'Pledgor client ID', 'type' => 'text'],
                ['name' => 'pledgee_dp_id', 'label' => 'Pledgee DP ID', 'type' => 'text'],
                ['name' => 'pledgee_client_id', 'label' => 'Pledgee client ID', 'type' => 'text'],
            ];
        }

        return $fields;
    }

    /**
     * The limits the registration form applies.
     */
    public function validate(Model $record, array $input, User $actor): array
    {
        $input = array_map(fn ($v) => is_string($v) ? (trim($v) ?: null) : $v, $input);
        $pledge = $record->kind === RegistrationKind::Pledge;
        $money = ['nullable', 'numeric', 'min:0', 'max:9999999999999999', 'decimal:0,2'];

        return Validator::make($input, [
            'reference' => ['nullable', 'string', 'max:60'],
            'amount' => $money,
            'security_name' => [$pledge ? 'required' : 'exclude', 'string', 'max:255'],
            'quantity' => [$pledge ? 'required' : 'exclude', 'integer', 'min:1'],
            'face_value' => [$pledge ? 'nullable' : 'exclude', ...array_slice($money, 1)],
            'depository' => [$pledge ? 'required' : 'exclude', Rule::in(['NSDL', 'CDSL'])],
            'pledgor_dp_id' => [$pledge ? 'nullable' : 'exclude', 'string', 'max:20'],
            'pledgor_client_id' => [$pledge ? 'nullable' : 'exclude', 'string', 'max:20'],
            'pledgee_dp_id' => [$pledge ? 'nullable' : 'exclude', 'string', 'max:20'],
            'pledgee_client_id' => [$pledge ? 'nullable' : 'exclude', 'string', 'max:20'],
        ])->validate();
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        $record->update($validated);
    }

    public function transactionId(Model $record): int
    {
        return $record->transaction_id;
    }
}
