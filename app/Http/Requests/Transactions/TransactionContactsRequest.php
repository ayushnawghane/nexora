<?php

namespace App\Http\Requests\Transactions;

use App\Enums\Recipient;
use App\Models\CompanyContact;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Step 2: the client's people who receive the engagement letter (at least one "To" with an email). */
class TransactionContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->transaction());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $transaction = $this->transaction();
        $current = $transaction->contacts()->pluck('company_contact_id')->all();

        return [
            'contacts' => ['required', 'array', 'min:1', 'max:50'],
            'contacts.*.company_contact_id' => ['required', 'integer', 'distinct',
                Rule::exists('company_contacts', 'id')
                    ->where('company_id', $transaction->company_id)
                    ->whereNull('deleted_at')
                    ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $current ?: [0]))],
            'contacts.*.recipient' => ['required', Rule::enum(Recipient::class)],
        ];
    }

    /**
     * @return list<\Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $toIds = collect($this->input('contacts'))
                ->where('recipient', Recipient::To->value)
                ->pluck('company_contact_id');

            if ($toIds->isEmpty()) {
                $validator->errors()->add('contacts', 'Mark at least one contact as "To".');
            } elseif (! CompanyContact::query()->whereIn('id', $toIds)->whereNotNull('email')->exists()) {
                $validator->errors()->add('contacts', 'At least one "To" contact needs an email address; the letter is sent by email.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'contacts.required' => 'Add at least one contact.',
            'contacts.*.company_contact_id.exists' => 'Pick active contacts of this company.',
            'contacts.*.company_contact_id.distinct' => 'Each contact can only be added once.',
        ];
    }

    public function transaction(): Transaction
    {
        /** @var Transaction */
        return $this->route('transaction');
    }
}
