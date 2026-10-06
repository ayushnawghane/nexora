<?php

namespace App\Actions\Transactions;

use App\Enums\EscalationType;
use App\Enums\FeeAmountType;
use App\Enums\FeeKind;
use App\Models\FeeSchedulePeriod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveFees
{
    public function __construct(private readonly RegenerateSchedule $regenerate) {}

    /**
     * Saves the enabled fee lines (removing disabled ones) and rebuilds the schedule.
     *
     * @param  array<string, array<string, mixed>>  $fees  validated TransactionFeesRequest `fees`
     */
    public function handle(Transaction $transaction, array $fees, User $actor): void
    {
        DB::transaction(function () use ($transaction, $fees, $actor) {
            foreach (FeeKind::cases() as $kind) {
                $fee = $fees[$kind->value];

                if (! $fee['enabled']) {
                    $billed = FeeSchedulePeriod::query()->whereNotNull('invoice_id')
                        ->whereHas('feeLine', fn ($q) => $q->where('transaction_id', $transaction->id)->where('kind', $kind))->exists();
                    if ($billed) {
                        throw ValidationException::withMessages(["fees.{$kind->value}.enabled" => "The {$kind->label()} has been billed, so it can't be removed."]);
                    }
                    $transaction->feeLines()->where('kind', $kind)->delete();

                    continue;
                }

                $percent = $fee['amount_type'] === FeeAmountType::Percent->value;
                $escalates = $fee['escalation_type'] !== EscalationType::None->value;

                $transaction->feeLines()->updateOrCreate(['kind' => $kind], [
                    'amount_type' => $fee['amount_type'],
                    'amount' => $percent ? null : $fee['amount'],
                    'percent' => $percent ? $fee['percent'] : null,
                    'annual_amount' => '0', // resolved by RegenerateSchedule
                    'basis' => $fee['basis'],
                    'frequency' => $fee['frequency'],
                    'start_reference' => $fee['start_reference'],
                    'start_date' => $fee['start_date'],
                    'timing' => $fee['timing'],
                    'escalation_type' => $fee['escalation_type'],
                    'escalation_value' => $escalates ? $fee['escalation_value'] : null,
                    'escalation_every_years' => $escalates ? $fee['escalation_every_years'] : null,
                ]);
            }

            $transaction->updated_by = $actor->id;
            $this->regenerate->handle($transaction);
        });
    }
}
