<?php

namespace App\Legacy\Importers;

use App\Enums\AllotmentKind;
use App\Enums\CouponType;
use App\Enums\DayCount;
use App\Enums\HolidayConvention;
use App\Enums\IsinPaymentKind;
use App\Enums\IsinPaymentStatus;
use App\Enums\Listing;
use App\Enums\PaymentFrequency;
use App\Enums\Placement;
use App\Enums\RedemptionBasis;
use App\Legacy\Importer;
use App\Legacy\Models\LegacyRecord;
use App\Models\DealIsin;
use App\Models\DocumentFile;
use App\Models\IsinAllotment;
use App\Models\IsinPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Support\IndianIdentifiers;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ISINs of the imported DT deals: Stack's current ISIN tables (the `_new` ones; the older copies
 * are no longer used by Stack), their allotments with the depository credit, and the interest and
 * principal schedules with what was paid. Needs the transactions area first.
 *
 * - An ISIN that isn't a valid ISIN (Stack holds test entries such as "test opi") is reported and
 *   left out, with its schedule.
 * - The same ISIN twice on one deal is merged into the first; so is the same due date twice in one
 *   schedule (the settled one wins).
 * - Statuses: Due → due, Paid / Delayed Payment → paid, Default → defaulted, Redeemed Earlier →
 *   redeemed earlier. Amounts are kept as Stack recorded them (often empty for older payments).
 */
class IsinImporter extends Importer
{
    private const PRODUCT = 4;

    /** Dates after this year are Stack's placeholders for perpetual ISINs (Nexora's forms stop at 2100). */
    private const LAST_YEAR = 2100;

    private int $importUser;

    /** @var array<int, int> Stack ISIN (schedule) id => Nexora ISIN id */
    private array $isinIds = [];

    /** @var array<int, array<string, int>> Nexora deal id => ISIN code => Nexora ISIN id */
    private array $isinByCode = [];

    /** @var array<int, LegacyRecord> upload_file rows by id */
    private array $uploads = [];

    public function area(): string
    {
        return 'isin';
    }

    public function description(): string
    {
        return 'DT deals\' ISINs, allotments and interest / principal schedules with payments';
    }

    protected function import(): void
    {
        $user = $this->importUser();
        if ($user === null) {
            return;
        }
        $this->importUser = $user;

        $dealIds = LegacyRecord::from('transaction')->where('product_id', self::PRODUCT)->where('is_deleted', 0)->pluck('id')->all();
        $deals = Transaction::query()->whereIn('legacy_id', $dealIds)->get(['id', 'ulid', 'legacy_id', 'created_at'])->keyBy('legacy_id');

        $this->isins($deals);
        $this->allotments($deals);
        $this->payments($deals, IsinPaymentKind::Interest);
        $this->payments($deals, IsinPaymentKind::Principal);
    }

    /**
     * @param  Collection<int|string, Transaction>  $deals
     */
    private function isins(Collection $deals): void
    {
        foreach (LegacyRecord::from('mon_payment_schedule_new')->whereIn('con_id', $deals->keys()->all())->orderBy('id')->get() as $row) {
            $this->report->read('ISINs');
            $code = Str::upper((string) preg_replace('/\s+/', '', (string) $row->text('isin')));
            if ((int) $row->getAttribute('active') !== 1) {
                $this->report->reject('ISINs', $row->id, "{$code}: removed in Stack.");

                continue;
            }
            if (! IndianIdentifiers::isIsin($code)) {
                $this->report->reject('ISINs', $row->id, "\"{$row->text('isin')}\" isn't a valid ISIN.");

                continue;
            }
            /** @var Transaction $deal */
            $deal = $deals->get($row->ref('con_id'));
            if (isset($this->isinByCode[$deal->id][$code])) {
                $this->isinIds[(int) $row->id] = $this->isinByCode[$deal->id][$code];
                $this->report->warn('ISINs', $row->id, "{$code} is on the deal twice in Stack; merged.");

                continue;
            }

            // Stack marks a perpetual ISIN with maturity 9999-09-30; Nexora leaves its maturity blank.
            $maturity = $row->date('payment_redumtion_date');
            if ($maturity !== null && (int) substr($maturity, 0, 4) > self::LAST_YEAR) {
                $this->report->warn('ISINs', $row->id, "{$code}: maturity {$maturity} in Stack (perpetual); left blank.");
                $maturity = null;
            }

            $couponText = (string) $row->text('coupon_desc');
            $couponRate = is_numeric(rtrim($couponText, '% ')) && (float) rtrim($couponText, '% ') < 100 ? rtrim($couponText, '% ') : null;
            $series = $row->text('seriesname');

            /** @var DealIsin $isin */
            $isin = $this->upsert(DealIsin::class, (int) $row->id, [
                'transaction_id' => $deal->id,
                'isin' => $code,
                'series_name' => $series === '-' ? null : $series,
                'listing' => match ((int) ($row->getAttribute('listing_status') ?? -1)) {
                    0 => Listing::Listed,
                    1 => Listing::Unlisted,
                    default => null,
                },
                'exchange' => in_array($row->text('stock_exchange'), ['BSE', 'NSE', 'Both'], true) ? $row->text('stock_exchange') : null,
                'depository' => in_array($row->text('depository'), ['NSDL', 'CDSL', 'Both'], true) ? $row->text('depository') : null,
                'placement' => match ((int) $row->getAttribute('placement_type')) {
                    1 => Placement::Private,
                    2 => Placement::Public,
                    3 => Placement::Rights,
                    default => null,
                },
                'allotment_date' => $row->date('allotmentdate'),
                'maturity_date' => $maturity,
                'coupon_type' => match (Str::lower((string) $row->text('base_coupon_rate'))) {
                    'fixed' => CouponType::Fixed,
                    'zero' => CouponType::Zero,
                    'variable' => CouponType::Variable,
                    'ref linked' => CouponType::Linked,
                    default => null,
                },
                'coupon_rate' => $couponRate,
                'coupon_description' => $couponRate === null && $couponText !== '' && Str::upper($couponText) !== 'NA' ? mb_substr($couponText, 0, 255) : null,
                'interest_frequency' => PaymentFrequency::fromStack((int) $row->getAttribute('interest_frequency')),
                'principal_frequency' => PaymentFrequency::fromStack((int) $row->getAttribute('principal_frequency')),
                'day_count' => match ((string) $row->text('couponrate')) {
                    '1' => DayCount::Thirty360,
                    '2' => DayCount::Actual365,
                    '3' => DayCount::ActualActual,
                    default => (int) $row->getAttribute('year_convention_int') === 365 ? DayCount::Actual365 : null,
                },
                'holiday_convention' => match (Str::lower((string) ($row->text('principal_weekend') ?? $row->text('interest_weekend')))) {
                    'precede' => HolidayConvention::Preceding,
                    'succeed' => HolidayConvention::Following,
                    default => null,
                },
                'put_date' => $this->optionDate($row, 'put_date'),
                'call_date' => $this->optionDate($row, 'call_date'),
                'comments' => collect([$row->text('comments'), $row->text('int_comments')])->filter()->implode("\n") ?: null,
                'created_by' => $this->idFor(User::class, $row->ref('user_id')) ?? $this->importUser,
                'created_at' => $row->date('ts') ?? $deal->created_at,
            ], 'ISINs');

            $this->isinIds[(int) $row->id] = (int) $isin->id;
            $this->isinByCode[$deal->id][$code] = (int) $isin->id;
        }
    }

    /**
     * @param  Collection<int|string, Transaction>  $deals
     */
    private function allotments(Collection $deals): void
    {
        $rows = LegacyRecord::from('mon_isin_details_new')->whereIn('con_id', $deals->keys()->all())->orderBy('id')->get();
        $credits = LegacyRecord::from('mon_payment_listing_new')->whereIn('allotment_id', $rows->pluck('id'))->where('is_active', 1)->orderBy('id')->get()->groupBy('allotment_id');
        $this->uploads = $this->uploadRows($credits->flatten()->pluck('upload_id'));
        $initial = [];

        foreach ($rows as $row) {
            $this->report->read('allotments');
            /** @var Transaction $deal */
            $deal = $deals->get($row->ref('con_id'));
            $isinId = $this->isinIds[$row->ref('mon_id') ?? 0] ?? null;
            if ($isinId === null) {
                $this->report->reject('allotments', $row->id, 'Its ISIN wasn\'t imported.');

                continue;
            }
            $quantity = (int) $row->getAttribute('qty_subscribed');
            $faceValue = (string) $row->getAttribute('face_value');
            if ((int) $row->getAttribute('is_active') !== 1 || $quantity <= 0 || ! is_numeric($faceValue) || (float) $faceValue <= 0) {
                $this->report->reject('allotments', $row->id, (int) $row->getAttribute('is_active') !== 1 ? 'Removed in Stack.' : 'No face value or quantity allotted in Stack.');

                continue;
            }

            $kind = $row->text('type') === 'AA' ? AllotmentKind::Additional : AllotmentKind::Initial;
            if ($kind === AllotmentKind::Initial && isset($initial[$isinId])) {
                $kind = AllotmentKind::Additional;
                $this->report->warn('allotments', $row->id, 'A second initial allotment for the same ISIN; recorded as an additional allotment.');
            }
            if ($kind === AllotmentKind::Initial) {
                $initial[$isinId] = true;
            }

            $amount = is_numeric((string) $row->getAttribute('subscription_total')) && (float) $row->getAttribute('subscription_total') > 0
                ? BigDecimal::of((string) $row->getAttribute('subscription_total'))->toScale(2, RoundingMode::HalfUp)
                : BigDecimal::of($faceValue)->multipliedBy($quantity)->toScale(2, RoundingMode::HalfUp);
            /** @var LegacyRecord|null $credit */
            $credit = $credits->get($row->id)?->last();

            /** @var IsinAllotment $allotment */
            $allotment = $this->upsert(IsinAllotment::class, (int) $row->id, [
                'deal_isin_id' => $isinId,
                'kind' => $kind,
                'allotment_date' => $row->date('allotment_date') ?? DealIsin::query()->whereKey($isinId)->value('allotment_date') ?? $deal->created_at?->toDateString(),
                'issue_opened_on' => $row->date('issue_opening_date'),
                'issue_closed_on' => $row->date('issue_closing_date'),
                'face_value' => BigDecimal::of($faceValue)->toScale(2, RoundingMode::HalfUp)->__toString(),
                'quantity_offered' => (int) $row->getAttribute('qty_issued') > 0 ? (int) $row->getAttribute('qty_issued') : null,
                'quantity_allotted' => $quantity,
                'amount' => (string) $amount,
                'credit_depository' => in_array($credit?->text('type'), ['NSDL', 'CDSL'], true) ? $credit->text('type') : null,
                'credited_on' => $credit?->date('listing_date'),
                'created_by' => $this->idFor(User::class, $row->ref('created_by')) ?? $this->importUser,
                'created_at' => $row->date('created_date') ?? $deal->created_at,
            ], 'allotments');

            if ($credit && $credit->ref('upload_id')) {
                $this->file($allotment, $deal, (int) $credit->ref('upload_id'), 'allotment files');
            }
        }
    }

    /**
     * @param  Collection<int|string, Transaction>  $deals
     */
    private function payments(Collection $deals, IsinPaymentKind $kind): void
    {
        $interest = $kind === IsinPaymentKind::Interest;
        $entity = $interest ? 'interest payments' : 'principal payments';
        $c = $interest
            ? ['table' => 'mon_paymt_interst_sch_new', 'due' => 'in_due_date', 'status' => 'in_status', 'paid' => 'in_paid_date', 'remark' => 'in_remark', 'amount' => 'intrest_total_amnt', 'fv' => 'interest_face_value', 'qty' => 'interest_qty', 'basis' => 'intrest_redemp_type']
            : ['table' => 'mon_paymt_prin_sch_new', 'due' => 'due_date', 'status' => 'status', 'paid' => 'paid_date', 'remark' => 'prin_remark', 'amount' => 'prin_total_amnt', 'fv' => 'prin_face_value', 'qty' => 'prin_qty', 'basis' => 'redemp_type'];
        $prefix = $interest ? 'i' : 'p';

        $existing = IsinPayment::query()->where('legacy_key', 'like', "{$prefix}:%")->get()->keyBy('legacy_key');

        // Stack sometimes has a due date twice in a schedule. Pick the row to keep before saving
        // anything (the first settled one, else the first), so each date is written once.
        $first = []; // ISIN id => due date => first Stack row id
        $winner = []; // ISIN id => due date => Stack row id kept
        $settled = []; // Stack row id => whether it was settled
        foreach (LegacyRecord::from($c['table'])->whereIn('con_id', $deals->keys()->all())->where(fn ($q) => $q->where('active', 1)->orWhereNull('active'))
            ->orderBy('id')->get(['id', 'pay_schedule_id', $c['due'], $c['status']]) as $row) {
            $isinId = $this->isinIds[$row->ref('pay_schedule_id') ?? 0] ?? null;
            $due = $row->date($c['due']);
            if ($isinId === null || $due === null) {
                continue;
            }
            $dueOn = substr($due, 0, 10);
            $first[$isinId][$dueOn] ??= (int) $row->id;
            $kept = $winner[$isinId][$dueOn] ?? null;
            if ($kept === null || (! ($settled[$kept] ?? false) && $this->status($row->text($c['status'])) !== IsinPaymentStatus::Due)) {
                $winner[$isinId][$dueOn] = (int) $row->id;
            }
            $settled[(int) $row->id] = $this->status($row->text($c['status'])) !== IsinPaymentStatus::Due;
        }

        LegacyRecord::from($c['table'])->whereIn('con_id', $deals->keys()->all())->orderBy('id')->chunk(5000, function (Collection $rows) use ($deals, $kind, $entity, $c, $prefix, $existing, $first, $winner) {
            $this->uploads = $this->uploadRows($rows->pluck('upload_id')->merge($rows->pluck('dlt_upload_id')));

            foreach ($rows as $row) {
                $this->report->read($entity);
                $isinId = $this->isinIds[$row->ref('pay_schedule_id') ?? 0] ?? null;
                $due = $row->date($c['due']);
                $tooLate = $due !== null && (int) substr($due, 0, 4) > self::LAST_YEAR;
                if ($isinId === null || $due === null || $tooLate || (int) ($row->getAttribute('active') ?? 1) !== 1) {
                    $this->report->reject($entity, $row->id, match (true) {
                        $isinId === null => 'Its ISIN wasn\'t imported.',
                        $due === null => 'No due date.',
                        $tooLate => "Due {$due}: Stack's dates for a perpetual ISIN run to 9999; Nexora keeps them up to ".self::LAST_YEAR.'.',
                        default => 'Removed in Stack.',
                    });

                    continue;
                }
                /** @var Transaction $deal */
                $deal = $deals->get($row->ref('con_id'));
                $status = $this->status($row->text($c['status']));
                $dueOn = substr($due, 0, 10);

                if ($winner[$isinId][$dueOn] !== (int) $row->id) {
                    $this->report->warn($entity, $row->id, "{$dueOn} is in the schedule twice in Stack; merged.");

                    continue;
                }

                // Keyed by the row kept; an earlier import may have keyed it by the first row.
                $key = "{$prefix}:{$row->id}";
                $payment = $existing->get($key) ?? $existing->get("{$prefix}:{$first[$isinId][$dueOn]}") ?? new IsinPayment;
                $payment->forceFill([
                    'legacy_key' => $key,
                    'deal_isin_id' => $isinId,
                    'kind' => $kind,
                    'due_on' => $dueOn,
                    ...$this->outcome($row, $c, $kind, $status),
                    'created_by' => $this->idFor(User::class, $row->ref('created_by') ?? $row->ref('user_id')) ?? $this->importUser,
                    'created_at' => $payment->created_at ?? $row->date('created_at') ?? $deal->created_at,
                ]);
                $this->save($payment, $entity);

                foreach (array_filter([$row->ref('upload_id'), $row->ref('dlt_upload_id')]) as $uploadId) {
                    $this->file($payment, $deal, (int) $uploadId, "{$entity} files");
                }
            }
        });
    }

    private function status(?string $stack): IsinPaymentStatus
    {
        return match (Str::lower((string) $stack)) {
            'paid', 'delayed payment' => IsinPaymentStatus::Paid,
            'default' => IsinPaymentStatus::Defaulted,
            'redeemed earlier', 'reedemed earlier' => IsinPaymentStatus::RedeemedEarly,
            default => IsinPaymentStatus::Due,
        };
    }

    /**
     * Stack keeps put and call dates as a PHP-serialised list of strings (Y-m-d, sometimes d/m/Y),
     * usually one and often empty. The first date is kept; any further ones are reported.
     */
    private function optionDate(LegacyRecord $row, string $column): ?string
    {
        $raw = (string) $row->text($column);
        $values = str_starts_with($raw, 'a:') ? @unserialize($raw, ['allowed_classes' => false]) : [$raw];
        $dates = [];
        foreach (is_array($values) ? $values : [] as $value) {
            $value = trim((string) (is_scalar($value) ? $value : ''));
            foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
                $date = $value === '' ? false : \DateTimeImmutable::createFromFormat("!{$format}", $value);
                if ($date && $date->format($format) === $value) {
                    $dates[] = $date->format('Y-m-d');

                    break;
                }
            }
        }
        if (count($dates) > 1) {
            $this->report->warn('ISINs', $row->id, ucfirst(str_replace('_', ' ', $column)).'s '.implode(', ', $dates)." in Stack; kept {$dates[0]}.");
        }

        return $dates[0] ?? null;
    }

    /**
     * @param  array<string, string>  $c  the Stack columns of this schedule
     * @return array<string, mixed>
     */
    private function outcome(LegacyRecord $row, array $c, IsinPaymentKind $kind, IsinPaymentStatus $status): array
    {
        $money = function (?string $value): ?string {
            $clean = preg_replace('/[^0-9.]/', '', (string) $value);

            return $clean !== '' && is_numeric($clean) && (float) $clean < 1e16 ? number_format((float) $clean, 2, '.', '') : null;
        };
        $settled = $status !== IsinPaymentStatus::Due;
        $principal = $kind === IsinPaymentKind::Principal;

        return [
            'status' => $status,
            'paid_on' => $status === IsinPaymentStatus::Paid ? ($row->date($c['paid']) ?? $row->date($c['due'])) : null,
            'amount' => $settled ? $money($row->text($c['amount'])) : null,
            'face_value' => $settled && $principal ? $money($row->text($c['fv'])) : null,
            'quantity' => $settled && $principal ? $money($row->text($c['qty'])) : null,
            'redemption_basis' => $settled && $principal ? match ((string) $row->text($c['basis'])) {
                'fr' => RedemptionBasis::Full,
                'fv' => RedemptionBasis::FaceValue,
                'qty' => RedemptionBasis::Quantity,
                default => null,
            } : null,
            'remark' => trim(collect([$row->text($c['remark']), Str::lower((string) $row->text($c['status'])) === 'delayed payment' ? 'Delayed payment (Stack)' : null])->filter()->implode(' · ')) ?: null,
            'recorded_by' => $settled ? $this->idFor(User::class, $row->ref('updated_by')) ?? $this->importUser : null,
            'recorded_at' => $settled ? ($row->date('updated_at') ?? $row->date('created_at')) : null,
        ];
    }

    /**
     * @param  Collection<array-key, mixed>  $ids
     * @return array<int, LegacyRecord>
     */
    private function uploadRows(Collection $ids): array
    {
        $rows = [];
        foreach ($ids->filter(fn ($id) => (int) $id > 0)->map(fn ($id) => (int) $id)->unique()->chunk(5000) as $chunk) {
            foreach (LegacyRecord::from('upload_file')->whereIn('id', $chunk->all())->get(['id', 'name', 'path', 'created_by', 'created_at']) as $row) {
                $rows[(int) $row->id] = $row;
            }
        }

        return $rows;
    }

    private function file(IsinAllotment|IsinPayment $owner, Transaction $deal, int $uploadId, string $entity): void
    {
        $this->report->read($entity);
        $upload = $this->uploads[$uploadId] ?? null;
        if ($upload === null) {
            $this->report->reject($entity, $uploadId, "Upload #{$uploadId} isn't in Stack's file list.");

            return;
        }
        $key = ['attachable_type' => $owner::class, 'attachable_id' => $owner->id, 'legacy_id' => $uploadId];
        $file = DocumentFile::query()->where($key)->first() ?? (new DocumentFile)->forceFill($key);
        $file->forceFill([
            'original_name' => mb_substr($upload->text('name') ?? basename((string) $upload->text('path')) ?: 'file', 0, 255),
            'uploaded_by' => $this->idFor(User::class, $upload->ref('created_by')) ?? $this->importUser,
            'created_at' => $file->created_at ?? $upload->date('created_at') ?? $deal->created_at,
        ]);
        $this->save($file, $entity);

        $root = config('legacy.uploads_path');
        if ($file->isAvailable() || ! $root) {
            return;
        }
        $relative = ltrim(str_replace('\\', '/', (string) $upload->text('path')), '/');
        $source = rtrim((string) $root, '/\\').DIRECTORY_SEPARATOR.$relative;
        if ($relative === '' || str_contains($relative, '..') || ! is_file($source)) {
            $this->report->warn("{$entity} (copy)", $uploadId, "Not found in the uploads copy: {$relative}");

            return;
        }
        if ($this->report->dryRun) {
            $this->report->saved("{$entity} (copy)", 'created');

            return;
        }
        $extension = Str::lower(pathinfo($relative, PATHINFO_EXTENSION));
        $path = "deals/{$deal->ulid}/isin/stack-{$uploadId}".($extension !== '' ? ".{$extension}" : '');
        Storage::disk('local')->put($path, (string) file_get_contents($source));
        $file->forceFill(['path' => $path, 'size' => filesize($source) ?: null, 'mime' => mime_content_type($source) ?: null])->save();
        $this->report->saved("{$entity} (copy)", 'created');
    }

    private function save(Model $model, string $entity): void
    {
        $outcome = ! $model->exists ? 'created' : ($model->isDirty() ? 'updated' : 'unchanged');
        if ($outcome !== 'unchanged') {
            $model->save();
        }
        $this->report->saved($entity, $outcome);
    }
}
