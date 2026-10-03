<?php

namespace App\Http\Requests\Transactions;

use App\Enums\Origin;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Step 1: who the deal is for and who at Beacon owns it. */
class TransactionBasicsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Transaction|null $transaction */
        $transaction = $this->route('transaction');

        return $transaction
            ? $this->user()->can('update', $transaction)
            : $this->user()->can('create', Transaction::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['brief' => trim((string) $this->input('brief')) ?: null]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Transaction|null $transaction */
        $transaction = $this->route('transaction');
        // A master that has since been deactivated may stay on the draft that already uses it.
        $activeOrCurrent = fn (string $table, string $column) => Rule::exists($table, 'id')
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $transaction->{$column} ?? 0));

        return [
            'company_id' => ['required', 'integer', $activeOrCurrent('companies', 'company_id')],
            'transaction_type_id' => ['nullable', 'integer', $activeOrCurrent('transaction_types', 'transaction_type_id')],
            'lead_source_id' => ['nullable', 'integer', $activeOrCurrent('lead_sources', 'lead_source_id')],
            'arranger_id' => ['nullable', 'integer', $activeOrCurrent('arrangers', 'arranger_id')],
            'vertical_team_id' => ['required', 'integer', $activeOrCurrent('vertical_teams', 'vertical_team_id')],
            'relationship_manager_id' => ['required', 'integer', $activeOrCurrent('users', 'relationship_manager_id')],
            'signatory_id' => ['nullable', 'integer', Rule::exists('users', 'id')
                ->whereNull('deleted_at')->where('is_authorised_signatory', true)
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $transaction->signatory_id ?? 0))],
            'origin' => ['required', Rule::enum(Origin::class)],
            'brief' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.exists' => 'Pick an active company.',
            'signatory_id.exists' => 'Pick an active authorised signatory.',
            'relationship_manager_id.exists' => 'Pick an active user.',
        ];
    }

    public function attributes(): array
    {
        return [
            'company_id' => 'company', 'transaction_type_id' => 'transaction type', 'lead_source_id' => 'lead source',
            'arranger_id' => 'arranger', 'vertical_team_id' => 'vertical team',
            'relationship_manager_id' => 'relationship manager', 'signatory_id' => 'signatory',
        ];
    }
}
