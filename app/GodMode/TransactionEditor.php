<?php

namespace App\GodMode;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for editors whose record is the transaction itself (addressed by its ULID).
 *
 * @extends Editor<Transaction>
 */
abstract class TransactionEditor extends Editor
{
    public function modelClass(): string
    {
        return Transaction::class;
    }

    public function find(string $id): Transaction
    {
        return Transaction::query()->where('ulid', $id)->firstOrFail();
    }

    protected function routeParameters(Model $record): array
    {
        return ['transaction' => $record];
    }

    public function transactionId(Model $record): int
    {
        return $record->id;
    }

    public function companyId(Model $record): int
    {
        return $record->company_id;
    }
}
