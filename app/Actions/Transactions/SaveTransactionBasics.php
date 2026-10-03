<?php

namespace App\Actions\Transactions;

use App\Enums\Origin;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveTransactionBasics
{
    /**
     * Creates a DT draft or updates its basics. Changing the company drops the chosen contacts,
     * since they belong to the old company.
     *
     * @param  array<string, mixed>  $data  validated TransactionBasicsRequest data
     */
    public function handle(array $data, User $actor, ?Transaction $transaction = null): Transaction
    {
        return DB::transaction(function () use ($data, $actor, $transaction) {
            $transaction ??= new Transaction([
                'product_id' => Product::query()->where('code', Product::DEBENTURE_TRUSTEE)->value('id'),
                'created_by' => $actor->id,
            ]);

            $companyChanged = $transaction->exists && (int) $data['company_id'] !== $transaction->company_id;

            $transaction->fill([
                'company_id' => $data['company_id'],
                'transaction_type_id' => $data['transaction_type_id'] ?? null,
                'lead_source_id' => $data['lead_source_id'] ?? null,
                'arranger_id' => $data['arranger_id'] ?? null,
                'vertical_team_id' => $data['vertical_team_id'],
                'relationship_manager_id' => $data['relationship_manager_id'],
                'signatory_id' => $data['signatory_id'] ?? null,
                'origin' => Origin::from($data['origin']),
                'brief' => $data['brief'] ?? null,
                'updated_by' => $actor->id,
            ])->save();

            if ($companyChanged) {
                $transaction->contacts()->delete();
            }

            return $transaction;
        });
    }
}
