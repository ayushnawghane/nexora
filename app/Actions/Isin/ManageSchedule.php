<?php

namespace App\Actions\Isin;

use App\Actions\Documents\StoresDocumentFiles;
use App\Enums\HolidayConvention;
use App\Enums\IsinPaymentKind;
use App\Enums\IsinPaymentStatus;
use App\Enums\PaymentFrequency;
use App\Enums\RedemptionBasis;
use App\Models\DealIsin;
use App\Models\IsinPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Isin\PaymentDates;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * An ISIN's interest and principal schedule: adding due dates (generated, uploaded or one by one),
 * moving or removing a due date that's still due, and recording what happened on it.
 */
class ManageSchedule
{
    use StoresDocumentFiles;

    public function __construct(private readonly PaymentDates $dates) {}

    /**
     * Adds the due dates of a frequency, from the first due date to maturity.
     *
     * @return int dates added (dates already in the schedule are skipped)
     */
    public function generate(DealIsin $isin, IsinPaymentKind $kind, CarbonImmutable $first, PaymentFrequency $frequency, User $actor): int
    {
        if ($isin->maturity_date === null) {
            throw ValidationException::withMessages(['first_due_on' => 'Set the ISIN\'s maturity date first.']);
        }
        if ($first->isAfter($isin->maturity_date)) {
            throw ValidationException::withMessages(['first_due_on' => 'The first due date is after maturity.']);
        }

        $dates = $this->dates->generate($first, CarbonImmutable::parse($isin->maturity_date), $frequency, $isin->holiday_convention ?? HolidayConvention::None);

        return $this->add($isin, [$kind->value => $dates], $actor, 'first_due_on');
    }

    /**
     * Adds the dates in a schedule file in Stack's format: a CSV with "Principal Schedule" (or
     * Stack's "Principle Schedule") and "Interest Schedule" columns. Every row is checked first;
     * one bad date rejects the whole file.
     *
     * @return int dates added
     */
    public function upload(DealIsin $isin, UploadedFile $file, User $actor): int
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'The file could not be read.']);
        }
        $header = array_map(fn ($h) => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), fgetcsv($handle) ?: []);
        $principalColumn = array_search('principal schedule', $header, true);
        if ($principalColumn === false) {
            $principalColumn = array_search('principle schedule', $header, true);
        }
        $interestColumn = array_search('interest schedule', $header, true);
        if ($principalColumn === false && $interestColumn === false) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'The file needs a "Principal Schedule" and/or an "Interest Schedule" column.']);
        }

        $found = ['principal' => [], 'interest' => []];
        $errors = [];
        for ($line = 2; ($row = fgetcsv($handle)) !== false; $line++) {
            foreach (['principal' => $principalColumn, 'interest' => $interestColumn] as $kind => $column) {
                $value = $column === false ? '' : trim((string) ($row[$column] ?? ''));
                if ($value === '') {
                    continue;
                }
                $date = $this->parseDate($value);
                if ($date === null) {
                    $errors[] = "Line {$line}: \"{$value}\" isn't a date.";

                    continue;
                }
                $found[$kind][] = $date;
            }
            if (count($errors) >= 10) {
                break;
            }
        }
        fclose($handle);

        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => implode(' ', $errors)]);
        }
        if ($found['principal'] === [] && $found['interest'] === []) {
            throw ValidationException::withMessages(['file' => 'The file has no dates.']);
        }

        return $this->add($isin, $found, $actor, 'file');
    }

    public function addOne(DealIsin $isin, IsinPaymentKind $kind, CarbonImmutable $dueOn, User $actor): int
    {
        return $this->add($isin, [$kind->value => [$dueOn]], $actor, 'due_on');
    }

    /**
     * Moves a due date that's still due. The first original date and the reason are kept.
     */
    public function move(IsinPayment $payment, CarbonImmutable $dueOn, string $reason): IsinPayment
    {
        return $this->lockedPayment($payment, 'due_on', function (IsinPayment $locked, DealIsin $isin) use ($dueOn, $reason) {
            $this->checkInRange($isin, [$dueOn], 'due_on');
            if ($isin->payments()->where('kind', $locked->kind)->whereDate('due_on', $dueOn)->whereKeyNot($locked->id)->exists()) {
                throw ValidationException::withMessages(['due_on' => 'The schedule already has a '.$locked->kind->label().' payment on that date.']);
            }
            $locked->update([
                'original_due_on' => $locked->original_due_on ?? $locked->due_on,
                'due_on' => $dueOn,
                'due_date_reason' => $reason,
            ]);

            return $locked;
        });
    }

    public function remove(IsinPayment $payment): void
    {
        $this->lockedPayment($payment, 'payment', function (IsinPayment $locked) {
            $locked->delete();

            return $locked;
        });
    }

    /**
     * Records what happened on a due date: paid (with the date and amount), defaulted, or redeemed
     * earlier. Principal payments say how they redeem the debentures. Settled rows don't change
     * again (God Mode corrects them).
     *
     * @param  array<string, mixed>  $data  validated RecordPaymentRequest data
     * @param  list<UploadedFile>  $files
     */
    public function record(IsinPayment $payment, IsinPaymentStatus $status, array $data, array $files, User $actor): IsinPayment
    {
        if ($status === IsinPaymentStatus::Due) {
            throw ValidationException::withMessages(['status' => 'Choose what happened on this date.']);
        }

        return $this->withFiles(fn () => $this->lockedPayment($payment, 'status', function (IsinPayment $locked, DealIsin $isin, Transaction $deal) use ($status, $data, $files, $actor) {
            $paidOn = ! empty($data['paid_on']) ? CarbonImmutable::parse($data['paid_on']) : null;
            if ($status === IsinPaymentStatus::Paid && ($paidOn === null || empty($data['amount']))) {
                throw ValidationException::withMessages(['amount' => 'Enter the date paid and the amount.']);
            }
            if ($locked->kind === IsinPaymentKind::Principal && $status === IsinPaymentStatus::Paid && empty($data['redemption_basis'])) {
                throw ValidationException::withMessages(['redemption_basis' => 'Say how the principal was redeemed.']);
            }
            $locked->update([
                'status' => $status,
                'paid_on' => $paidOn,
                'amount' => $data['amount'] ?? null,
                'face_value' => $locked->kind === IsinPaymentKind::Principal ? ($data['face_value'] ?? null) : null,
                'quantity' => $locked->kind === IsinPaymentKind::Principal ? ($data['quantity'] ?? null) : null,
                'redemption_basis' => $locked->kind === IsinPaymentKind::Principal && ! empty($data['redemption_basis']) ? RedemptionBasis::from($data['redemption_basis']) : null,
                'remark' => $data['remark'] ?? null,
                'recorded_by' => $actor->id,
                'recorded_at' => now(),
            ]);
            foreach ($files as $file) {
                $this->attach($locked, $deal, 'isin', $file, $actor);
            }

            return $locked;
        }));
    }

    /**
     * @param  array<string, list<CarbonImmutable>>  $byKind
     * @param  string  $field  the form field a problem is reported against
     */
    private function add(DealIsin $isin, array $byKind, User $actor, string $field): int
    {
        return DB::transaction(function () use ($isin, $byKind, $actor, $field) {
            $deal = Transaction::query()->whereKey($isin->transaction_id)->lockForUpdate()->firstOrFail();
            $locked = DealIsin::query()->whereKey($isin->id)->lockForUpdate()->firstOrFail();
            if (! $deal->isOpenDeal()) {
                throw ValidationException::withMessages([$field => 'The ISINs of a closed deal can\'t be changed.']);
            }

            $added = 0;
            foreach ($byKind as $kind => $dates) {
                $this->checkInRange($locked, $dates, $field);
                $existing = $locked->payments()->where('kind', $kind)->pluck('due_on')->map(fn ($d) => CarbonImmutable::parse($d)->toDateString())->all();
                foreach (array_unique(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates)) as $date) {
                    if (in_array($date, $existing, true)) {
                        continue;
                    }
                    $locked->payments()->create([
                        'kind' => $kind,
                        'due_on' => $date,
                        'status' => IsinPaymentStatus::Due,
                        'created_by' => $actor->id,
                    ]);
                    $added++;
                }
            }

            return $added;
        });
    }

    /**
     * Due dates must fall between allotment and maturity, when the ISIN has them.
     *
     * @param  list<CarbonImmutable>  $dates
     */
    private function checkInRange(DealIsin $isin, array $dates, string $field): void
    {
        foreach ($dates as $date) {
            if ($isin->allotment_date && $date->isBefore($isin->allotment_date)) {
                throw ValidationException::withMessages([$field => "{$date->format('d M Y')} is before the allotment date ({$isin->allotment_date->format('d M Y')})."]);
            }
            if ($isin->maturity_date && $date->isAfter($isin->maturity_date)) {
                throw ValidationException::withMessages([$field => "{$date->format('d M Y')} is after maturity ({$isin->maturity_date->format('d M Y')})."]);
            }
        }
    }

    /**
     * @param  callable(IsinPayment, DealIsin, Transaction): IsinPayment  $change
     */
    private function lockedPayment(IsinPayment $payment, string $field, callable $change): IsinPayment
    {
        $run = function () use ($payment, $field, $change) {
            $isinId = $payment->deal_isin_id;
            $deal = Transaction::query()->whereKey(DealIsin::query()->whereKey($isinId)->value('transaction_id'))->lockForUpdate()->firstOrFail();
            $isin = DealIsin::query()->whereKey($isinId)->lockForUpdate()->firstOrFail();
            $locked = IsinPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! $deal->isOpenDeal()) {
                throw ValidationException::withMessages([$field => 'The ISINs of a closed deal can\'t be changed.']);
            }
            if ($locked->status !== IsinPaymentStatus::Due) {
                throw ValidationException::withMessages([$field => "This payment is already {$locked->status->label()}."]);
            }

            return $change($locked, $isin, $deal);
        };

        return DB::transactionLevel() > 0 ? $run() : DB::transaction($run);
    }

    /** A date in one of the formats schedule files use; impossible dates (31-02) are refused. */
    private function parseDate(string $value): ?CarbonImmutable
    {
        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'd.m.Y', 'd-M-Y', 'd M Y', 'j-n-Y', 'j/n/Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat("!{$format}", $value);
            } catch (Throwable) {
                continue;
            }
            if ($date instanceof CarbonImmutable && $date->format($format) === $value && $date->year >= 2000 && $date->year <= 2100) {
                return $date;
            }
        }

        return null;
    }
}
