<?php

namespace App\Actions\Letters;

use App\Enums\DealStatus;
use App\Enums\FeeStartReference;
use App\Enums\TransactionStatus;
use App\Models\EngagementLetter;
use App\Models\FeeLine;
use App\Models\NumberSequence;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Letters\EngagementLetterRenderer;
use App\Support\FinancialYear;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class IssueEngagementLetter
{
    public function __construct(private readonly EngagementLetterRenderer $renderer) {}

    /**
     * Issues the first engagement letter for an approved transaction: takes the next EL number of
     * the financial year (one sequence shared by all products, row-locked so numbers never collide),
     * sets the deal code, stores the letter and its PDF as version 1 and makes the transaction Active,
     * with the deal starting at Preliminary.
     * Everything happens in one DB transaction; on failure no number is used up.
     */
    public function handle(Transaction $transaction, User $actor, CarbonImmutable $elDate): EngagementLetter
    {
        return DB::transaction(function () use ($transaction, $actor, $elDate) {
            /** @var Transaction $locked */
            $locked = Transaction::query()->whereKey($transaction->id)->with(['product', 'feeLines'])->lockForUpdate()->firstOrFail();
            $this->guard($locked, $elDate);

            $fy = FinancialYear::short($elDate);
            $code = $locked->product->code;
            $number = NumberSequence::next("el:{$fy}");

            $locked->el_number = "BTL/{$code}/EL/{$fy}/{$number}";
            $locked->el_date = Carbon::parse($elDate->toDateString());
            $locked->deal_code = "{$code}/{$fy}/{$locked->id}";
            $locked->updated_by = $actor->id;
            $locked->transitionTo(TransactionStatus::Active);
            $locked->deal_status = DealStatus::Preliminary;
            $locked->deal_status_since = Carbon::parse($elDate->toDateString());
            $locked->save();
            $locked->statusChanges()->create([
                'to_status' => DealStatus::Preliminary,
                'effective_on' => $elDate,
                'changed_by' => $actor->id,
            ]);

            $html = $this->renderer->html($locked, $locked->el_number, $elDate);
            $path = "letters/{$locked->ulid}/v1.pdf";
            Storage::disk('local')->put($path, $this->renderer->pdf($html));

            return $locked->engagementLetters()->create([
                'version' => 1,
                'el_number' => $locked->el_number,
                'el_date' => $elDate,
                'body_html' => $html,
                'pdf_path' => $path,
                'generated_by' => $actor->id,
            ]);
        });
    }

    private function guard(Transaction $transaction, CarbonImmutable $elDate): void
    {
        if ($transaction->status !== TransactionStatus::Approved) {
            throw ValidationException::withMessages(['el_date' => 'Only an approved transaction can be issued an engagement letter.']);
        }
        if (! $transaction->isScheduleVerified()) {
            throw ValidationException::withMessages(['el_date' => 'The fee schedule must be verified before the letter is issued.']);
        }
        if ($elDate->isAfter(today())) {
            throw ValidationException::withMessages(['el_date' => 'The EL date can\'t be in the future.']);
        }
        if ($transaction->approved_at && $elDate->lt($transaction->approved_at->copy()->startOfDay())) {
            throw ValidationException::withMessages(['el_date' => 'The EL date can\'t be before the approval date ('.$transaction->approved_at->format('d M Y').').']);
        }

        $tied = $transaction->feeLines->first(fn (FeeLine $f) => $f->start_reference === FeeStartReference::ElDate && ! $f->start_date->isSameDay($elDate));
        if ($tied) {
            throw ValidationException::withMessages(['el_date' => "The {$tied->kind->label()} runs from the EL date and its approved schedule starts on {$tied->start_date->format('d M Y')}. Issue the letter with that date, or revise the transaction."]);
        }
    }
}
