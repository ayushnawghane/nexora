<?php

namespace App\Actions\Isin;

use App\Actions\Documents\StoresDocumentFiles;
use App\Enums\AllotmentKind;
use App\Models\DealIsin;
use App\Models\IsinAllotment;
use App\Models\Transaction;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveIsin
{
    use StoresDocumentFiles;

    /**
     * Adds an ISIN to a deal, or corrects one. An ISIN is on a deal once. Maturity can't move to
     * before a scheduled date that's already settled.
     *
     * @param  array<string, mixed>  $data  validated IsinRequest data
     */
    public function handle(Transaction $deal, ?DealIsin $isin, array $data, User $actor): DealIsin
    {
        return DB::transaction(function () use ($deal, $isin, $data, $actor) {
            /** @var Transaction $locked */
            $locked = Transaction::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isOpenDeal()) {
                throw ValidationException::withMessages(['isin' => 'The ISINs of a closed deal can\'t be changed.']);
            }
            $taken = $locked->isins()->where('isin', $data['isin'])->when($isin, fn ($q) => $q->whereKeyNot($isin->id))->exists();
            if ($taken) {
                throw ValidationException::withMessages(['isin' => 'This ISIN is already on the deal.']);
            }

            if ($isin) {
                $isin = DealIsin::query()->whereKey($isin->id)->lockForUpdate()->firstOrFail();
                $lastSettled = $isin->payments()->where('status', '!=', 'due')->reorder()->max('due_on');
                if ($lastSettled && ! empty($data['maturity_date']) && CarbonImmutable::parse($data['maturity_date'])->isBefore(CarbonImmutable::parse($lastSettled))) {
                    throw ValidationException::withMessages(['maturity_date' => 'Maturity can\'t be before a payment already settled ('.CarbonImmutable::parse($lastSettled)->format('d M Y').').']);
                }
                $isin->update($data);

                return $isin;
            }

            return $locked->isins()->create([...$data, 'created_by' => $actor->id]);
        });
    }

    /**
     * Records debentures allotted under the ISIN; the amount is face value × quantity. An ISIN has
     * one initial allotment; further tranches come after it.
     *
     * @param  array<string, mixed>  $data  validated AllotmentRequest data
     */
    public function allot(DealIsin $isin, array $data, ?UploadedFile $creditProof, User $actor): IsinAllotment
    {
        return $this->withFiles(function () use ($isin, $data, $creditProof, $actor) {
            $deal = Transaction::query()->whereKey($isin->transaction_id)->lockForUpdate()->firstOrFail();
            $locked = DealIsin::query()->whereKey($isin->id)->lockForUpdate()->firstOrFail();
            if (! $deal->isOpenDeal()) {
                throw ValidationException::withMessages(['allotment_date' => 'The ISINs of a closed deal can\'t be changed.']);
            }

            $kind = AllotmentKind::from($data['kind']);
            $initial = $locked->allotments()->where('kind', AllotmentKind::Initial)->first();
            if ($kind === AllotmentKind::Initial && $initial) {
                throw ValidationException::withMessages(['kind' => 'This ISIN already has its initial allotment; add a further tranche instead.']);
            }
            if ($kind === AllotmentKind::Additional && ! $initial) {
                throw ValidationException::withMessages(['kind' => 'Record the initial allotment first.']);
            }
            $date = CarbonImmutable::parse($data['allotment_date']);
            if ($initial && $date->isBefore($initial->allotment_date)) {
                throw ValidationException::withMessages(['allotment_date' => 'A further tranche can\'t be allotted before the initial allotment.']);
            }
            if ($locked->maturity_date && $date->isAfter($locked->maturity_date)) {
                throw ValidationException::withMessages(['allotment_date' => 'The allotment date is after the ISIN\'s maturity.']);
            }

            $amount = BigDecimal::of((string) $data['face_value'])->multipliedBy((string) $data['quantity_allotted'])->toScale(2, RoundingMode::HalfUp);
            $allotment = $locked->allotments()->create([
                ...$data,
                'amount' => (string) $amount,
                'created_by' => $actor->id,
            ]);
            if ($kind === AllotmentKind::Initial && $locked->allotment_date === null) {
                $locked->update(['allotment_date' => $date]);
            }
            if ($creditProof) {
                $this->attach($allotment, $deal, 'isin', $creditProof, $actor);
            }

            return $allotment;
        });
    }
}
