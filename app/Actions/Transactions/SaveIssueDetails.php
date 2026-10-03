<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

class SaveIssueDetails
{
    public function __construct(private readonly RegenerateSchedule $regenerate) {}

    /**
     * Saves the issue details and instrument split, then rebuilds the fee schedule (percentage fees
     * and the tenure depend on them).
     *
     * @param  array<string, mixed>  $data  validated TransactionIssueRequest data
     */
    public function handle(Transaction $transaction, array $data, User $actor): void
    {
        DB::transaction(function () use ($transaction, $data, $actor) {
            $transaction->issueDetail()->updateOrCreate([], [
                'listing' => $data['listing'],
                'issue_type' => $data['issue_type'],
                'is_secured' => (bool) $data['is_secured'],
                'is_rated' => (bool) $data['is_rated'],
                'base_issue_size' => $data['base_issue_size'],
                'green_shoe_size' => $data['green_shoe_size'],
                'total_issue_size' => (string) BigDecimal::of($data['base_issue_size'])->plus($data['green_shoe_size'])->toScale(2),
                'tenure_months' => (int) $data['tenure_months'],
                'tenure_days' => (int) $data['tenure_days'],
            ]);

            $transaction->instruments()->delete();
            $transaction->instruments()->createMany(array_values(array_filter(
                $data['instruments'],
                fn (array $row) => BigDecimal::of($row['base_amount'])->plus($row['green_shoe_amount'])->isPositive(),
            )));

            $transaction->updated_by = $actor->id;
            $this->regenerate->handle($transaction);
        });
    }
}
