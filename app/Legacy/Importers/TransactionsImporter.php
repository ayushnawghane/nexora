<?php

namespace App\Legacy\Importers;

use App\Enums\DealStatus;
use App\Enums\EscalationType;
use App\Enums\FeeAmountType;
use App\Enums\FeeBasis;
use App\Enums\FeeFrequency;
use App\Enums\FeeKind;
use App\Enums\FeeStartReference;
use App\Enums\FeeTiming;
use App\Enums\Instrument;
use App\Enums\IssueType;
use App\Enums\Listing;
use App\Enums\Origin;
use App\Enums\Recipient;
use App\Enums\StatusApprovalTeam;
use App\Enums\StatusRequestState;
use App\Enums\TransactionStatus;
use App\Legacy\Importer;
use App\Legacy\Models\LegacyRecord;
use App\Models\Arranger;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\CompanyContact;
use App\Models\CompanyGstin;
use App\Models\DealStatusChange;
use App\Models\DealStatusRequest;
use App\Models\EngagementLetter;
use App\Models\FeeLine;
use App\Models\FeeSchedulePeriod;
use App\Models\LeadSource;
use App\Models\NumberSequence;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\VerticalTeam;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Debenture trustee deals (legacy product 4) with everything hanging off them. Needs every other
 * area first.
 *
 * Decided with the project owner (2026-10-03):
 *  - Fees are mapped where Stack's data is complete enough; Stack's billing periods are kept exactly
 *    as billed (never recalculated). Deals whose fees can't be mapped come without them.
 *  - Engagement letter versions arrive as records; their PDFs are attached later from Stack's
 *    uploads folder ("PDF not copied yet" until then).
 *  - Deals with a pending "Requested …" status arrive at their current status with an open status
 *    request, for Management and Accounts to finish in Nexora.
 * Deals deleted in Stack are not imported.
 */
class TransactionsImporter extends Importer
{
    private const PRODUCT = 4;

    /** Stack status text => [Nexora deal status, or 'draft' / 'approved' before an EL]. */
    private const STATUS = [
        '' => 'draft', 'draft' => 'draft', 'proposal' => 'draft', 'approved' => 'approved',
        'preliminary' => 'preliminary', 'application' => 'preliminary', 'documentation' => 'documentation',
        'live' => 'live', 'hold' => 'hold', 'deferred' => 'hold', 'default' => 'defaulted',
        'redeemed' => 'redeemed', 'appoved redemption - with no dues' => 'redeemed', 'requested redemption - approved' => 'redeemed',
        'cancelled' => 'cancelled', 'binned' => 'cancelled', 'closed' => 'closed',
        'foreclosed' => 'foreclosed', 'surrendered' => 'surrendered', 'transferred' => 'transferred',
    ];

    /** Pending Stack requests => the status they ask for. */
    private const REQUESTED = [
        'requested redemption' => DealStatus::Redeemed,
        'requested closure' => DealStatus::Closed,
        'requested cancellation' => DealStatus::Cancelled,
        'requested transfer' => DealStatus::Transferred,
    ];

    private int $importUser;

    private int $product;

    /** @var array<int, string> Stack status id => lower-case status text */
    private array $statusNames = [];

    /** @var Collection<int, LegacyRecord> master_frequency rows */
    private Collection $options;

    public function area(): string
    {
        return 'transactions';
    }

    public function description(): string
    {
        return 'DT deals: issue, contacts, fees and billed periods, letters, status history, billing; EL number sequences';
    }

    protected function import(): void
    {
        $rows = LegacyRecord::from('transaction')->where('product_id', self::PRODUCT)->orderBy('id')->get();
        if ($rows->isNotEmpty()) {
            $this->importUser = $this->systemUser();
            $this->product = (int) ($this->idFor(Product::class, self::PRODUCT)
                ?? throw new \RuntimeException('Import the organisation area first: the DEB product is missing.'));
        }
        $this->statusNames = LegacyRecord::from('transaction_status_master')->get()
            ->mapWithKeys(fn (LegacyRecord $r) => [(int) $r->getKey() => Str::lower((string) $r->text('status'))])->all();
        $this->options = LegacyRecord::from('master_frequency')->get();

        $children = $this->children($rows->pluck('id')->all());

        foreach ($rows as $row) {
            $this->report->read('transactions');
            if ($row->isLegacyDeleted()) {
                $this->report->reject('transactions', $row->id, 'Deleted in Stack.');

                continue;
            }
            $this->transaction($row, $children);
        }

        $this->sequences();
        $this->reconcile($rows);
    }

    /**
     * Child rows of every DT deal, loaded once and grouped by deal.
     *
     * @param  list<int>  $ids
     * @return array<string, Collection<array-key, mixed>> groups of legacy rows, keyed by deal id
     */
    private function children(array $ids): array
    {
        $by = fn (string $table, string $key = 'con_id') => LegacyRecord::from($table)->whereIn($key, $ids)->where('is_deleted', 0)->orderBy('id')->get()->groupBy($key);

        return [
            'issue' => $by('issue_details'),
            'contacts' => $by('transaction_contact'),
            'acceptance' => $by('acceptance_fees'),
            'service' => $by('service_fees'),
            'periods' => $by('master_cl_schedules'),
            'letters' => $by('el_versioning'),
            'uploads' => LegacyRecord::from('upload_file')
                ->whereIn('id', LegacyRecord::from('el_versioning')->whereIn('con_id', $ids)->whereNotNull('upload_id')->pluck('upload_id'))
                ->get(['id', 'path'])->groupBy('id'),
            'billing' => $by('transaction_billing_address'),
            'requests' => LegacyRecord::from('update_status')->whereIn('con_id', $ids)->where('is_deleted', 0)->where('is_active', 1)->where('is_approved', 0)->orderBy('id')->get()->groupBy('con_id'),
            'log' => LegacyRecord::from('transaction_status_log')->whereIn('tran_id', $ids)->orderBy('id')->get()->groupBy('tran_id'),
        ];
    }

    /**
     * @param  array<string, Collection<array-key, mixed>>  $children  groups of legacy rows, keyed by deal id
     */
    private function transaction(LegacyRecord $row, array $children): void
    {
        $id = (int) $row->id;
        $companyId = $this->idFor(Company::class, $row->ref('company_id'));
        if ($companyId === null) {
            $this->report->reject('transactions', $id, "Its company (legacy {$row->ref('company_id')}) wasn't imported.");

            return;
        }

        $elNumber = Str::upper((string) $row->text('cl_no')) ?: null;
        if ($elNumber !== null && Transaction::query()->withTrashed()->where('el_number', $elNumber)->where(fn ($q) => $q->whereNull('legacy_id')->orWhere('legacy_id', '!=', $id))->exists()) {
            $this->report->warn('transactions', $id, "EL number {$elNumber} is already another deal's; left empty here.");
            $elNumber = null;
        }
        $elDate = $row->date('cl_date') ?? $row->date('offer_date') ?? substr((string) $row->date('created_at'), 0, 10) ?: null;

        [$status, $dealStatus, $requested] = $this->status($row, $children['requests']->get($id), $elNumber !== null);
        $statusSince = $this->statusSince($children['log']->get($id), $dealStatus) ?? $elDate;

        $transaction = $this->upsert(Transaction::class, $id, [
            'product_id' => $this->product,
            'company_id' => $companyId,
            'status' => $status,
            'deal_status' => $dealStatus,
            'deal_status_since' => $dealStatus ? $statusSince : null,
            'el_number' => $elNumber,
            'el_date' => $elNumber ? $elDate : null,
            'deal_code' => $elNumber ? $this->dealCode($row, $elDate) : null,
            'transaction_type_id' => $this->idFor(TransactionType::class, $row->ref('transaction_type_id')),
            'lead_source_id' => $this->idFor(LeadSource::class, $row->ref('lead_id1')),
            'arranger_id' => $this->idFor(Arranger::class, $row->ref('arranger_id')),
            'vertical_team_id' => $this->idFor(VerticalTeam::class, $row->ref('team_id')),
            'relationship_manager_id' => $this->idFor(User::class, $row->ref('rm_id')),
            'signatory_id' => $this->idFor(User::class, $row->ref('signatory_id')),
            'origin' => Str::upper((string) $row->text('originated_by')) === 'BD' ? Origin::BusinessDevelopment : Origin::Operations,
            'brief' => $row->text('transaction_brief'),
            'schedule_verified_at' => (int) $row->getAttribute('is_schedule_verified') === 1 ? ($row->date('updated_at') ?? $row->date('created_at') ?? now()) : null,
            'schedule_verified_by' => (int) $row->getAttribute('is_schedule_verified') === 1 ? $this->importUser : null,
            'closed_at' => $status === TransactionStatus::Closed ? ($row->date('deal_closed_date') ?? $row->date('updated_at')) : null,
            'created_by' => $this->idFor(User::class, $row->ref('created_by')) ?? $this->importUser,
            'updated_by' => $this->idFor(User::class, $row->ref('updated_by')),
            'created_at' => $row->date('created_at'),
            'updated_at' => $row->date('updated_at') ?? $row->date('created_at'),
        ], 'transactions');

        $this->issue($transaction, $row, $children['issue']->get($id)?->last());
        $this->contacts($transaction, $children['contacts']->get($id) ?? collect());
        $this->fees($transaction, $children['acceptance']->get($id)?->last(), $children['service']->get($id)?->last(), $children['periods']->get($id) ?? collect());
        if ($transaction->deal_status !== null) {
            $this->letters($transaction, $children['letters']->get($id) ?? collect(), $children['uploads']);
            $this->history($transaction, $children['log']->get($id) ?? collect());
            $this->request($transaction, $children['requests']->get($id)?->last(), $requested);
            $this->billing($transaction, $row, $children['billing']->get($id)?->last(), $children['contacts']->get($id) ?? collect());
        }
    }

    /**
     * Stack's free-text status, mapped. A "Requested …" status is the deal's current status (from its
     * open request, or its status id) plus the status asked for.
     *
     * @param  Collection<int, LegacyRecord>|null  $requests
     * @return array{0: TransactionStatus, 1: DealStatus|null, 2: DealStatus|null}
     */
    private function status(LegacyRecord $row, ?Collection $requests, bool $hasEl): array
    {
        $text = Str::lower((string) $row->text('status'));
        $requested = self::REQUESTED[$text] ?? null;

        if ($requested !== null) {
            $previous = $requests?->last()?->ref('previous_status') ?? $row->ref('status_id');
            $current = self::STATUS[$this->statusNames[$previous] ?? ''] ?? 'live';
            $text = in_array($current, ['draft', 'approved'], true) ? 'live' : $current;
        } else {
            $text = self::STATUS[$text] ?? null;
            if ($text === null) {
                $this->report->warn('transactions', $row->id, "Unknown Stack status \"{$row->text('status')}\"; imported as Live.");
                $text = 'live';
            }
        }

        if ($text === 'draft' || ($text === 'approved' && ! $hasEl)) {
            return [$text === 'draft' ? TransactionStatus::Draft : TransactionStatus::Approved, null, null];
        }

        $deal = $text === 'approved' ? DealStatus::Preliminary : DealStatus::from($text);
        if (! $hasEl) {
            $this->report->warn('transactions', $row->id, "{$deal->label()} in Stack but has no EL number.");
        }

        return [$deal->isFinal() ? TransactionStatus::Closed : TransactionStatus::Active, $deal, $requested];
    }

    /**
     * When the deal reached its current status, from Stack's status log.
     *
     * @param  Collection<int, LegacyRecord>|null  $log
     */
    private function statusSince(?Collection $log, ?DealStatus $current): ?string
    {
        if ($current === null || $log === null) {
            return null;
        }
        $match = $log->reverse()->first(fn (LegacyRecord $l) => (self::STATUS[$this->statusNames[$l->ref('status_id')] ?? 'x'] ?? null) === $current->value);

        return $match ? substr((string) $match->date('created_date'), 0, 10) : null;
    }

    /** Stack's deal id when it has one, otherwise the same pattern: {product}/{FY}/{legacy id}. */
    private function dealCode(LegacyRecord $row, ?string $elDate): ?string
    {
        $code = Str::upper((string) $row->text('deal_id'));
        if (! preg_match('#^[A-Z0-9&\-]+/\d{2}-\d{2}/\d+$#', $code)) {
            if ($elDate === null) {
                return null;
            }
            $start = (int) substr($elDate, 0, 4) - ((int) substr($elDate, 5, 2) < 4 ? 1 : 0);
            $code = sprintf('DEB/%02d-%02d/%d', $start % 100, ($start + 1) % 100, $row->id);
        }

        $taken = Transaction::query()->withTrashed()->where('deal_code', $code)->where(fn ($q) => $q->whereNull('legacy_id')->orWhere('legacy_id', '!=', $row->id))->exists();

        return $taken ? null : $code;
    }

    private function issue(Transaction $transaction, LegacyRecord $row, ?LegacyRecord $issue): void
    {
        // Stack keeps the sizes in two places, and either can be empty or 0.00: the first positive wins.
        $positive = fn (mixed ...$values) => (string) (collect($values)->first(fn ($v) => is_numeric($v) && (float) $v > 0) ?? '0');
        $base = $positive($issue?->getAttribute('issue_pool_size'), $row->getAttribute('issue_pool_size'), $row->getAttribute('total_issue_size'));
        if (BigDecimal::of($base)->isZero()) {
            $this->report->warn('issue details', $row->id, 'No issue size in Stack; issue details not imported.');

            return;
        }
        $this->report->read('issue details');
        $green = $positive($issue?->getAttribute('gso_size'), $row->getAttribute('gso_size'));
        $months = (int) floor((float) $row->getAttribute('tenure_months'));
        $issueType = Str::lower((string) $row->text('issue_type'));
        if ($issueType === 'rights') {
            $this->report->warn('issue details', $row->id, 'Rights issue in Stack; recorded as a private placement.');
        }
        $secured = Str::lower((string) $row->text('secured'));
        if ($secured === 'secured/unsecured') {
            $this->report->warn('issue details', $row->id, 'Partly secured in Stack; recorded as secured.');
        }

        $values = [
            'listing' => Str::lower((string) $row->text('listed_unlisted')) === 'listed' ? Listing::Listed : Listing::Unlisted,
            'issue_type' => $issueType === 'public issue' ? IssueType::PublicIssue : IssueType::PrivatePlacement,
            'is_secured' => $secured !== 'unsecured',
            'is_rated' => Str::lower((string) $row->text('rated')) === 'yes',
            'base_issue_size' => (string) BigDecimal::of($base)->toScale(2, RoundingMode::HalfUp),
            'green_shoe_size' => (string) BigDecimal::of($green)->toScale(2, RoundingMode::HalfUp),
            'total_issue_size' => (string) BigDecimal::of($base)->plus($green)->toScale(2, RoundingMode::HalfUp),
            'tenure_months' => max(0, min(600, $months)),
            'tenure_days' => 0,
        ];
        $detail = $transaction->issueDetail()->first();
        $outcome = $detail === null ? 'created' : ($detail->fill($values)->isDirty() ? 'updated' : 'unchanged');
        $transaction->issueDetail()->updateOrCreate([], $values);
        $this->report->saved('issue details', $outcome);

        // The instrument split, when Stack recorded one; otherwise the whole issue as NCDs.
        $split = [];
        foreach (Instrument::cases() as $instrument) {
            $b = (string) ($issue?->getAttribute("{$instrument->value}_issue") ?? '0') ?: '0';
            $g = (string) ($issue?->getAttribute("{$instrument->value}_gso") ?? '0') ?: '0';
            if (BigDecimal::of($b)->plus($g)->isPositive()) {
                $split[$instrument->value] = ['base_amount' => (string) BigDecimal::of($b)->toScale(2, RoundingMode::HalfUp), 'green_shoe_amount' => (string) BigDecimal::of($g)->toScale(2, RoundingMode::HalfUp)];
            }
        }
        if ($split === []) {
            $split['ncd'] = ['base_amount' => $values['base_issue_size'], 'green_shoe_amount' => $values['green_shoe_size']];
        }
        $transaction->instruments()->whereNotIn('instrument', array_keys($split))->delete();
        foreach ($split as $instrument => $amounts) {
            $transaction->instruments()->updateOrCreate(['instrument' => $instrument], $amounts);
        }
    }

    /**
     * @param  Collection<int, LegacyRecord>  $rows
     */
    private function contacts(Transaction $transaction, Collection $rows): void
    {
        $sync = [];
        foreach ($rows as $row) {
            $recipient = Recipient::tryFrom(Str::lower((string) $row->text('recipient')));
            if ($recipient === null) {
                continue; // "na": a billing-only contact
            }
            $this->report->read('letter contacts');
            $contact = CompanyContact::query()->find($this->idFor(CompanyContact::class, $row->ref('contact_id')));
            if ($contact === null || $contact->company_id !== $transaction->company_id) {
                $this->report->reject('letter contacts', $row->id, $contact === null ? "Contact (legacy {$row->ref('contact_id')}) wasn't imported." : 'The contact belongs to another company.');

                continue;
            }
            $sync[$contact->id] = $recipient;
        }

        $current = $transaction->contacts()->get()->mapWithKeys(fn ($c) => [$c->company_contact_id => $c->recipient]);
        $transaction->contacts()->whereNotIn('company_contact_id', array_keys($sync) ?: [0])->delete();
        foreach ($sync as $contactId => $recipient) {
            $transaction->contacts()->updateOrCreate(['company_contact_id' => $contactId], ['recipient' => $recipient]);
            $this->report->saved('letter contacts', ! $current->has($contactId) ? 'created' : ($current[$contactId] === $recipient ? 'unchanged' : 'updated'));
        }
    }

    /**
     * @param  Collection<int, LegacyRecord>  $periods
     */
    private function fees(Transaction $transaction, ?LegacyRecord $acceptance, ?LegacyRecord $service, Collection $periods): void
    {
        $byTab = $periods->toBase()->groupBy(fn (LegacyRecord $p) => (int) $p->getAttribute('tab_id'));

        foreach ([FeeKind::Acceptance->value => [$acceptance, 1], FeeKind::Service->value => [$service, 2]] as $kind => [$row, $tab]) {
            if ($row === null) {
                continue;
            }
            $this->report->read('fee lines');
            $line = $this->feeLine($transaction, FeeKind::from($kind), $row, $tab, $byTab->get($tab) ?? collect());
            if ($line !== null) {
                $this->periods($line, $byTab->get($tab) ?? collect());
            } elseif (($byTab->get($tab) ?? collect())->isNotEmpty()) {
                $this->report->warn('billed periods', $transaction->legacy_id, ($byTab->get($tab)?->count() ?? 0)." {$kind} period(s) not imported: the fee itself couldn't be mapped.");
            }
        }

        foreach ($byTab->except([1, 2]) as $tab => $rows) {
            $this->report->warn('billed periods', $transaction->legacy_id, $rows->count()." period(s) of another fee type (Stack tab {$tab}) not imported: Nexora has acceptance and service fees only.");
        }
    }

    /**
     * Maps one Stack fee row. The option columns hold either the option's number within its list or
     * a master_frequency id, so both are tried and the option's text decides.
     *
     * @param  Collection<int, LegacyRecord>  $periods
     */
    private function feeLine(Transaction $transaction, FeeKind $kind, LegacyRecord $row, int $type, Collection $periods): ?FeeLine
    {
        $col = fn (string $acceptance, string $service) => $kind === FeeKind::Acceptance ? $acceptance : $service;
        $percent = $this->option($type, 6, $row->getAttribute($col('accept_amount_type', 'serv_amount_type'))) === 'amount (in percentage)'
            || (int) $row->getAttribute($col('accept_amount_type', 'serv_amount_type')) === 2;
        $amount = (string) $row->getAttribute($col('accpt_amount', 'service_amount'));
        $rate = (string) $row->getAttribute($col('accpt_perc', 'service_perc'));

        if ($percent ? ! is_numeric($rate) || (float) $rate <= 0 : ! is_numeric($amount) || (float) $amount <= 0) {
            $this->report->reject('fee lines', $row->id, "The {$kind->label()} has no ".($percent ? 'percentage' : 'amount').' in Stack.');

            return null;
        }

        $basis = match ($this->option($type, 1, $row->getAttribute($col('accpt_lavy', 'service_lavy')))) {
            'of subscribed amount', 'of subscribed amount in one or more tranches' => FeeBasis::SubscribedAmount,
            'of issue size in one or more tranches' => FeeBasis::IssueSizeTranches,
            'per tranche' => FeeBasis::PerTranche,
            'per security document' => FeeBasis::PerSecurityDocument,
            default => FeeBasis::IssueSize,
        };
        $frequency = $kind === FeeKind::Acceptance ? FeeFrequency::OneTime : match ($this->option($type, 2, $row->getAttribute('service_frequency'))) {
            'half yearly' => FeeFrequency::HalfYearly,
            'quarterly' => FeeFrequency::Quarterly,
            'monthly' => FeeFrequency::Monthly,
            'one time' => FeeFrequency::OneTime,
            default => FeeFrequency::Annual,
        };
        $reference = match ($this->option($type, 3, $row->getAttribute($col('accpt_effect_day', 'service_eff_day')))) {
            'dta/bta execution date', 'sta execution date', 'execution date of deed of assignment' => FeeStartReference::DtaExecution,
            'allotment date', 'deemed date of allotment' => FeeStartReference::AllotmentDate,
            'execution date of trust deed' => FeeStartReference::TrustDeedExecution,
            'custom date' => FeeStartReference::CustomDate,
            default => FeeStartReference::ElDate,
        };
        $timing = str_contains((string) $this->option($type, 4, $row->getAttribute($col('accpt_payment_term', 'service_pay_term'))), 'arrears') ? FeeTiming::Arrears : FeeTiming::Advance;

        $start = match ($reference) {
            FeeStartReference::CustomDate => $row->date($col('acpt_custom_date', 'service_custom_date')),
            FeeStartReference::ElDate => $transaction->el_date?->toDateString(),
            default => null,
        } ?? $periods->sortBy(fn (LegacyRecord $x) => $x->date('from_date'))->first()?->date('from_date') ?? $transaction->el_date?->toDateString();
        if ($start === null) {
            $this->report->reject('fee lines', $row->id, "The {$kind->label()} has no start date and the deal has no EL date.");

            return null;
        }

        $escalation = EscalationType::None;
        $escValue = null;
        $escYears = null;
        if ($kind === FeeKind::Service && (int) $row->getAttribute('service_escalation') === 1 && (float) $row->getAttribute('service_escalation_fees') > 0) {
            $escalation = (int) $row->getAttribute('service_escalation_type') === 2 ? EscalationType::Percent : EscalationType::Amount;
            $escValue = (string) BigDecimal::of((string) $row->getAttribute('service_escalation_fees'))->toScale(2, RoundingMode::HalfUp);
            $escYears = max(1, min(30, (int) $row->getAttribute('service_years') ?: 1));
        }

        $issueSize = (string) ($transaction->issueDetail()->value('total_issue_size') ?? '0');
        $annual = $percent
            ? BigDecimal::of($issueSize)->multipliedBy($rate)->dividedBy(100, 2, RoundingMode::HalfUp)
            : BigDecimal::of($amount)->toScale(2, RoundingMode::HalfUp);

        $values = [
            'amount_type' => $percent ? FeeAmountType::Percent : FeeAmountType::Fixed,
            'amount' => $percent ? null : (string) $annual,
            'percent' => $percent ? (string) BigDecimal::of($rate)->toScale(6, RoundingMode::HalfUp) : null,
            'annual_amount' => (string) $annual,
            'basis' => $basis,
            'frequency' => $frequency,
            'start_reference' => $reference,
            'start_date' => $start,
            'timing' => $timing,
            'escalation_type' => $escalation,
            'escalation_value' => $escValue,
            'escalation_every_years' => $escYears,
        ];

        $line = $transaction->feeLines()->firstOrNew(['kind' => $kind]);
        $line->forceFill($values);
        $outcome = ! $line->exists ? 'created' : ($line->isDirty() ? 'updated' : 'unchanged');
        if ($outcome !== 'unchanged') {
            $line->save();
        }
        $this->report->saved('fee lines', $outcome);

        return $line;
    }

    /** The text of a master_frequency option (lower case), from either kind of code Stack stored. */
    private function option(int $type, int $flag, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (int) $value;
        $row = $this->options->first(fn (LegacyRecord $o) => (int) $o->getKey() === $value && (int) $o->getAttribute('flag') === $flag)
            ?? $this->options->first(fn (LegacyRecord $o) => (int) $o->getAttribute('type') === $type && (int) $o->getAttribute('flag') === $flag && (int) $o->getAttribute('frequency_id') === $value);

        return $row ? Str::lower(trim((string) $row->getAttribute('frequency'))) : null;
    }

    /**
     * Stack's billed periods, kept exactly as billed.
     *
     * @param  Collection<int, LegacyRecord>  $rows
     */
    private function periods(FeeLine $line, Collection $rows): void
    {
        $sequence = 0;
        foreach ($rows->sortBy(fn (LegacyRecord $r) => $r->date('from_date').'|'.$r->id) as $row) {
            $this->report->read('billed periods');
            $from = $row->date('from_date');
            $to = $row->date('to_date');
            if ($from === null || $to === null) {
                $this->report->reject('billed periods', $row->id, 'No from or to date.');

                continue;
            }
            $days = (int) round((float) $row->getAttribute('no_of_days'));
            $inYear = (int) round((float) $row->getAttribute('no_of_days_in_year')) ?: 365;
            $base = (string) BigDecimal::of((string) ($row->getAttribute('base_amount') ?? '0') ?: '0')->toScale(2, RoundingMode::HalfUp);

            $this->upsert(FeeSchedulePeriod::class, (int) $row->id, [
                'fee_line_id' => $line->id,
                'sequence' => ++$sequence,
                'from_date' => $from,
                'to_date' => $to,
                'bill_date' => $row->date('bill_date') ?? $from,
                'days' => $days,
                'days_in_year' => $inYear,
                'base_amount' => $base,
                'amount' => (string) BigDecimal::of((string) ($row->getAttribute('applicable_fees') ?? $base) ?: $base)->toScale(2, RoundingMode::HalfUp),
                'financial_year' => (string) ($row->text('fy') ?? ''),
                'prorated' => $days < $inYear,
            ], 'billed periods');
        }
    }

    /**
     * @param  Collection<int, LegacyRecord>  $rows
     * @param  Collection<array-key, mixed>  $uploads  upload_file rows by id
     */
    private function letters(Transaction $transaction, Collection $rows, Collection $uploads): void
    {
        $version = 0;
        foreach ($rows as $row) {
            $this->report->read('letter versions');
            $number = Str::upper((string) ($row->text('cl_no_new') ?? $row->text('cl_no') ?? $transaction->el_number));
            if ($number === '' || $transaction->el_date === null) {
                $this->report->reject('letter versions', $row->id, 'No EL number or EL date.');

                continue;
            }
            $revision = (int) $row->getAttribute('revised_count');
            $letter = $this->upsert(EngagementLetter::class, (int) $row->id, [
                'transaction_id' => $transaction->id,
                'version' => ++$version,
                'el_number' => $number,
                'el_date' => $transaction->el_date->toDateString(),
                'reason' => $revision > 0 ? "Imported from Stack (revision {$revision})" : 'Imported from Stack',
                'generated_by' => $this->idFor(User::class, $row->ref('created_by')) ?? $this->importUser,
                'legacy_upload_id' => $row->ref('upload_id'),
                'created_at' => $row->date('created_at'),
                'updated_at' => $row->date('updated_at') ?? $row->date('created_at'),
            ], 'letter versions');

            $this->attachPdf($letter, $uploads->get($row->ref('upload_id') ?? 0)?->first());
        }
    }

    /**
     * Copies a letter's PDF from the copy of Stack's uploads folder (config legacy.uploads_path), if
     * one is configured and the file is there. Skipped in a dry run: files aren't rolled back.
     */
    private function attachPdf(EngagementLetter $letter, mixed $upload): void
    {
        $root = config('legacy.uploads_path');
        if ($letter->hasPdf() || ! $root || ! $upload instanceof LegacyRecord) {
            return;
        }
        $relative = ltrim(str_replace('\\', '/', (string) $upload->text('path')), '/');
        $source = rtrim((string) $root, '/\\').DIRECTORY_SEPARATOR.$relative;
        if ($relative === '' || str_contains($relative, '..') || ! is_file($source)) {
            $this->report->warn('letter PDFs', $letter->legacy_id, "PDF not found in the uploads copy: {$relative}");

            return;
        }
        if (! str_starts_with((string) file_get_contents($source, length: 5), '%PDF')) {
            $this->report->warn('letter PDFs', $letter->legacy_id, "{$relative} isn't a PDF; not attached.");

            return;
        }
        if ($this->report->dryRun) {
            $this->report->saved('letter PDFs', 'created');

            return;
        }

        $path = "letters/{$letter->transaction()->value('ulid')}/stack-v{$letter->version}.pdf";
        Storage::disk('local')->put($path, (string) file_get_contents($source));
        $letter->forceFill(['pdf_path' => $path])->save();
        $this->report->saved('letter PDFs', 'created');
    }

    /**
     * Stack's status log becomes the deal's status history.
     *
     * @param  Collection<int, LegacyRecord>  $rows
     */
    private function history(Transaction $transaction, Collection $rows): void
    {
        $previous = null;
        foreach ($rows as $row) {
            $text = self::STATUS[$this->statusNames[$row->ref('status_id')] ?? 'x'] ?? null;
            if ($text === null || in_array($text, ['draft', 'approved'], true) || $text === $previous?->value) {
                continue;
            }
            $this->report->read('status history');
            $status = DealStatus::from($text);
            $this->upsert(DealStatusChange::class, (int) $row->id, [
                'transaction_id' => $transaction->id,
                'from_status' => $previous,
                'to_status' => $status,
                'effective_on' => substr((string) $row->date('created_date'), 0, 10) ?: $transaction->el_date?->toDateString(),
                'changed_by' => $this->idFor(User::class, $row->ref('created_by')) ?? $this->importUser,
            ], 'status history');
            $previous = $status;
        }

        // Without a log, the deal's history starts at its current status.
        if ($previous === null && DealStatusChange::query()->where('transaction_id', $transaction->id)->doesntExist()) {
            $transaction->statusChanges()->create([
                'to_status' => $transaction->deal_status,
                'effective_on' => $transaction->deal_status_since ?? $transaction->el_date ?? now(),
                'changed_by' => $this->importUser,
            ]);
            $this->report->saved('status history', 'created');
        }
    }

    private function request(Transaction $transaction, ?LegacyRecord $row, ?DealStatus $requested): void
    {
        if ($requested === null) {
            return;
        }
        $this->report->read('open status requests');
        $from = $transaction->deal_status;
        if ($from === null || ! $from->canTransitionTo($requested)) {
            $this->report->reject('open status requests', $transaction->legacy_id, "Stack asked for {$requested->label()}, which a {$from?->label()} deal can't move to; raise it again in Nexora.");

            return;
        }
        $teams = $from->approvalTeamsFor($requested);
        $values = [
            'transaction_id' => $transaction->id,
            'from_status' => $from,
            'to_status' => $requested,
            'effective_on' => $row?->date('redemption_date') ?? substr((string) ($row?->date('created_at') ?? now()->toDateString()), 0, 10),
            'reason' => 'Imported from Stack: this request was waiting for approval there.',
            'noc_path' => null,
            'noc_name' => $row?->ref('upload_id') ? 'Stack upload #'.$row->ref('upload_id').' (not copied yet)' : null,
            'needs_management' => in_array(StatusApprovalTeam::Management, $teams, true),
            'needs_accounts' => in_array(StatusApprovalTeam::Accounts, $teams, true),
            'status' => StatusRequestState::Open,
            'requested_by' => $this->idFor(User::class, $row?->ref('created_by')) ?? $this->importUser,
        ];

        if ($row !== null) {
            $this->upsert(DealStatusRequest::class, (int) $row->id, $values, 'open status requests');

            return;
        }

        // Stack marked the deal as requested without keeping the request row: one open request per deal.
        $request = DealStatusRequest::query()->where('transaction_id', $transaction->id)->whereNull('legacy_id')->where('status', StatusRequestState::Open)->first() ?? new DealStatusRequest;
        $request->forceFill($values);
        $outcome = ! $request->exists ? 'created' : ($request->isDirty() ? 'updated' : 'unchanged');
        $request->save();
        $this->report->saved('open status requests', $outcome);
    }

    /**
     * @param  Collection<int, LegacyRecord>  $contacts
     */
    private function billing(Transaction $transaction, LegacyRecord $row, ?LegacyRecord $billing, Collection $contacts): void
    {
        $addressId = $this->idFor(CompanyAddress::class, $billing?->ref('address_id'));
        if ($addressId === null) {
            return;
        }
        $this->report->read('deal billing');
        $address = CompanyAddress::query()->findOrFail($addressId);
        if ($address->company_id !== $transaction->company_id) {
            $this->report->reject('deal billing', $transaction->legacy_id, 'The billing address belongs to another company.');

            return;
        }
        $gstin = CompanyGstin::query()->find($address->company_gstin_id ?? $this->idFor(CompanyGstin::class, $row->ref('gst_id')));
        if ($gstin !== null && ($gstin->company_id !== $transaction->company_id || $gstin->state_id !== $address->state_id)) {
            $gstin = null;
        }

        $values = [
            'company_address_id' => $address->id,
            'company_gstin_id' => $gstin?->id,
            'place_of_supply_state_id' => $gstin->state_id ?? $address->state_id,
            'updated_by' => $this->importUser,
        ];
        $current = $transaction->billing()->first();
        $transaction->billing()->updateOrCreate([], $values);
        $this->report->saved('deal billing', $current === null ? 'created' : 'unchanged');

        $ids = $contacts->filter(fn (LegacyRecord $c) => in_array(Str::lower((string) $c->text('billing_recipient')), ['to', 'cc'], true))
            ->map(fn (LegacyRecord $c) => $this->idFor(CompanyContact::class, $c->ref('contact_id')))
            ->filter()->unique()->values()->all();
        $transaction->billingContacts()->sync(CompanyContact::query()->whereKey($ids)->where('company_id', $transaction->company_id)->pluck('id')->all());
    }

    /**
     * EL numbers come from one sequence per financial year shared by all products, so the sequence
     * starts after the highest number Stack issued in that year (any product).
     */
    private function sequences(): void
    {
        $max = [];
        $deals = [];
        $scan = function (?string $number, bool $onDeal) use (&$max, &$deals): void {
            if ($number !== null && preg_match('#^BTL/[A-Z0-9&\-]+/EL/(\d{2}-\d{2})/(\d+)$#', Str::upper(trim($number)), $m)) {
                $max[$m[1]] = max($max[$m[1]] ?? 0, (int) $m[2]);
                if ($onDeal) {
                    $deals[$m[1]] = max($deals[$m[1]] ?? 0, (int) $m[2]);
                }
            }
        };
        LegacyRecord::from('transaction')->pluck('cl_no')->each(fn ($n) => $scan($n, true));
        LegacyRecord::from('el_versioning')->get(['cl_no', 'cl_no_new'])->each(function (LegacyRecord $r) use ($scan) {
            $scan($r->text('cl_no'), false);
            $scan($r->text('cl_no_new'), false);
        });

        // A letter version numbered far beyond any deal is probably a data error in Stack, but the
        // number may be printed on that PDF, so the sequence still skips past it; flag it for review.
        foreach ($max as $fy => $n) {
            if ($n > ($deals[$fy] ?? 0)) {
                $this->report->warn('EL sequences', $fy, "Letter versions in Stack go up to {$n}, deals only to ".($deals[$fy] ?? 0).'; the sequence continues after '.$n.' so no printed number is reused. Check those versions.');
            }
        }

        foreach ($max as $fy => $n) {
            $sequence = NumberSequence::query()->firstOrNew(['key' => "el:{$fy}"], ['last_value' => 0]);
            if ($sequence->last_value < $n) {
                $sequence->last_value = $n;
                $sequence->save();
            }
            $this->report->total("EL sequence {$fy} continues after", (string) $sequence->last_value);
        }
    }

    /**
     * @param  Collection<int, LegacyRecord>  $rows
     */
    private function reconcile(Collection $rows): void
    {
        $kept = $rows->reject(fn (LegacyRecord $r) => $r->isLegacyDeleted());
        $imported = Transaction::query()->whereIn('legacy_id', $kept->pluck('id'))->count();
        $this->report->total('DT deals in Stack (not deleted) / imported', "{$kept->count()} / {$imported}");

        $legacyBilled = LegacyRecord::from('master_cl_schedules')->whereIn('con_id', $kept->pluck('id'))->where('is_deleted', 0)->whereIn('tab_id', [1, 2])
            ->get(['applicable_fees'])->reduce(fn (BigDecimal $sum, LegacyRecord $r) => $sum->plus((string) ($r->getAttribute('applicable_fees') ?? '0') ?: '0'), BigDecimal::zero());
        $nexoraBilled = FeeSchedulePeriod::query()->whereNotNull('legacy_id')->pluck('amount')
            ->reduce(fn (BigDecimal $sum, $a) => $sum->plus((string) $a), BigDecimal::zero());
        $this->report->total('Billed periods, acceptance + service: Stack / Nexora (₹)', $legacyBilled->toScale(2, RoundingMode::HalfUp).' / '.$nexoraBilled->toScale(2, RoundingMode::HalfUp));
    }

    /** The user imported records are attributed to when Stack doesn't say who did something. */
    private function systemUser(): int
    {
        $user = User::query()->withTrashed()->firstOrNew(['emp_code' => 'LEGACY-IMPORT']);
        if (! $user->exists) {
            $user->forceFill([
                'name' => 'Stack import', 'email' => 'legacy-import@legacy.invalid', 'is_active' => false,
                'password' => Hash::make(Str::password(40)), 'must_change_password' => true,
            ])->save();
        }

        return (int) $user->id;
    }
}
