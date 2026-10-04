<?php

namespace App\Legacy\Importers;

use App\Enums\ConditionStatus;
use App\Enums\DealDocumentKind;
use App\Enums\DiligenceKind;
use App\Enums\OwnerIdType;
use App\Enums\RegistrationAction;
use App\Enums\RegistrationKind;
use App\Enums\RegistrationStatus;
use App\Enums\SecurityNature;
use App\Legacy\Importer;
use App\Legacy\Models\LegacyRecord;
use App\Models\AssetType;
use App\Models\ChargeType;
use App\Models\DealDiligenceItem;
use App\Models\DealDocument;
use App\Models\DealSecurity;
use App\Models\DocumentFile;
use App\Models\EmpanelledAgency;
use App\Models\LegalDocumentType;
use App\Models\SecurityRegistration;
use App\Models\SecurityRegistrationEvent;
use App\Models\SecurityType;
use App\Models\State;
use App\Models\Transaction;
use App\Models\User;
use App\Support\IndianIdentifiers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Security of the imported DT deals: the asset, charge and security type masters, empanelled
 * agencies, each deal's securities (Stack's per-document "legal compliance" details), their ROC,
 * CERSAI and pledge registrations with the filings, and the due diligence items with their files.
 * Needs the execution area first.
 *
 * - A security's kind comes from Stack's form type, else its document's type, else its asset type
 *   (immovable → mortgage, otherwise hypothecation).
 * - Registrations: each Stack mapping is one registration; its asset rows become the history
 *   (first row registered, later rows modified; satisfaction where Stack recorded it). Pledges add
 *   up pledged and unpledged quantities: fully unpledged means released.
 * - Due diligence "issued by" values are Stack's empanelled agencies.
 * - About 2,900 of Stack's rows are empty placeholders from its older ERP (no owner, description,
 *   charge, asset or securities); they're skipped.
 */
class SecurityImporter extends Importer
{
    private const PRODUCT = 4;

    private int $importUser;

    /** @var array<int, int> Stack security_mapping id => deal security id */
    private array $securityByMapping = [];

    /** @var array<int, int> Stack legal_compliance_documents_data id => deal security id */
    private array $securityByDetail = [];

    /** @var array<int, LegacyRecord> upload_file rows by id */
    private array $uploads = [];

    public function area(): string
    {
        return 'security';
    }

    public function description(): string
    {
        return 'Asset, charge and security types, empanelled agencies; DT deals\' securities, ROC/CERSAI/pledge registrations and due diligence';
    }

    protected function import(): void
    {
        $this->importUser = (int) (User::query()->withTrashed()->where('emp_code', 'LEGACY-IMPORT')->value('id')
            ?? throw new \RuntimeException('Import the transactions area first: the Stack import user is missing.'));

        $this->masters();

        $dealIds = LegacyRecord::from('transaction')->where('product_id', self::PRODUCT)->where('is_deleted', 0)->pluck('id')->all();
        $deals = Transaction::query()->whereIn('legacy_id', $dealIds)->get(['id', 'ulid', 'legacy_id', 'created_at'])->keyBy('legacy_id');

        $this->securities($deals);
        $this->registrations($deals);
        $this->diligence($deals);
    }

    // ------------------------------------------------------------------ masters

    private function masters(): void
    {
        $simple = function (string $table, string $column, string $class, string $entity): void {
            $seen = [];
            foreach (LegacyRecord::from($table)->orderBy('id')->get() as $row) {
                $this->report->read($entity);
                $name = $row->text($column);
                if ($name === null) {
                    $this->report->reject($entity, $row->id, 'No name.');

                    continue;
                }
                if (isset($seen[Str::lower($name)])) {
                    $this->alias($class, (int) $row->id, $seen[Str::lower($name)], "Same name: {$name}", $entity);

                    continue;
                }
                $this->claim($class, (int) $row->id, 'name', $name);
                $model = $this->upsert($class, (int) $row->id, ['name' => $name, 'is_active' => $row->isLegacyActive()], $entity);
                $seen[Str::lower($name)] = (int) $model->getKey();
            }
        };
        $simple('master_asset_type', 'type_asset', AssetType::class, 'asset types');
        $simple('master_type_charge', 'type_charge', ChargeType::class, 'charge types');

        $seen = [];
        foreach (LegacyRecord::from('master_security')->orderBy('id')->get() as $row) {
            $this->report->read('security types');
            $name = $row->text('security_name');
            if ($name === null) {
                $this->report->reject('security types', $row->id, 'No name.');

                continue;
            }
            if (isset($seen[Str::lower($name)])) {
                $this->alias(SecurityType::class, (int) $row->id, $seen[Str::lower($name)], "Same name: {$name}", 'security types');

                continue;
            }
            $this->claim(SecurityType::class, (int) $row->id, 'name', $name);
            $model = $this->upsert(SecurityType::class, (int) $row->id, [
                'name' => $name,
                'asset_type_id' => $this->idFor(AssetType::class, $row->ref('asset_type_id')),
                'is_active' => $row->isLegacyActive(),
            ], 'security types');
            $seen[Str::lower($name)] = (int) $model->getKey();
        }

        $codes = [];
        foreach (LegacyRecord::from('ea_master')->orderBy('id')->get() as $row) {
            $this->report->read('empanelled agencies');
            $name = $row->text('ea_name');
            if ($name === null) {
                $this->report->reject('empanelled agencies', $row->id, 'No name.');

                continue;
            }
            $code = Str::upper((string) ($row->text('ea_code') ?? "EA-{$row->id}"));
            $code = preg_replace('/[^A-Z0-9\-]/', '', $code) ?: "EA-{$row->id}";
            if (isset($codes[$code])) {
                $this->report->warn('empanelled agencies', $row->id, "Code {$code} is used twice in Stack; this one is EA-S{$row->id}.");
                $code = "EA-S{$row->id}";
            }
            $codes[$code] = true;
            $this->claim(EmpanelledAgency::class, (int) $row->id, 'code', $code);
            $this->upsert(EmpanelledAgency::class, (int) $row->id, [
                'code' => mb_substr($code, 0, 20),
                'name' => mb_substr($name, 0, 200),
                'city' => ctype_digit((string) $row->text('city')) ? null : $row->text('city'),
                'is_active' => $row->isLegacyActive(),
            ], 'empanelled agencies');
        }

        // The kind of security each legal document creates (Stack's document type).
        foreach (LegacyRecord::from('master_legal_documents')->where('con_id', 0)->where('parent_id', 0)->get(['id', 'type']) as $row) {
            $type = LegalDocumentType::withTrashed()->where('legacy_id', $row->id)->first();
            if ($type === null) {
                continue;
            }
            $nature = $this->natureFromType((int) $row->getAttribute('type'));
            if ($type->security_nature !== $nature) {
                $type->forceFill(['security_nature' => $nature])->save();
                $this->report->saved('legal document security kinds', 'updated');
            }
        }
    }

    private function natureFromType(int $type): ?SecurityNature
    {
        return match ($type) {
            2 => SecurityNature::Hypothecation,
            3 => SecurityNature::Mortgage,
            4 => SecurityNature::Pledge,
            5 => SecurityNature::Guarantee,
            default => null,
        };
    }

    // ------------------------------------------------------------------ securities

    /**
     * @param  Collection<int|string, Transaction>  $deals  by legacy id
     */
    private function securities(Collection $deals): void
    {
        $rows = LegacyRecord::from('legal_compliance_documents_data')->whereIn('con_id', $deals->keys()->all())->orderBy('id')->get();
        $mappings = LegacyRecord::from('security_mapping')->whereIn('legal_id', $rows->pluck('id'))->orderBy('id')->get()->groupBy('legal_id');
        $docTypes = LegacyRecord::from('master_legal_documents')->whereIn('id', $rows->pluck('legal_id')->unique())->pluck('type', 'id');
        $perDealDocuments = DealDocument::withTrashed()->whereNotNull('legacy_id')->pluck('id', 'legacy_id')->all();
        $chargeTypes = ChargeType::withTrashed()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [Str::lower($name) => $id])->all();
        $assetTypes = AssetType::withTrashed()->get(['id', 'name']);
        $securityTypes = SecurityType::withTrashed()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [Str::lower($name) => $id])->all();
        $states = State::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [Str::lower($name) => $id])->all();

        foreach ($rows as $row) {
            $this->report->read('securities');
            if (! $row->isLegacyActive()) {
                $this->report->reject('securities', $row->id, 'Removed in Stack.');

                continue;
            }
            $activeMappings = $mappings->get($row->id, collect())->filter(fn (LegacyRecord $m) => (int) $m->getAttribute('is_active') === 1 && (int) $m->getAttribute('is_deleted') === 0);
            $recorded = collect(['asset_owner', 'asset_office', 'street_name', 'area', 'charge_type', 'asset_type', 'type', 'pertaining_to', 'cin_pan_num'])
                ->contains(fn (string $c) => $row->text($c) !== null);
            if (! $recorded && $activeMappings->isEmpty()) {
                $this->report->reject('securities', $row->id, 'Nothing recorded in Stack.');

                continue;
            }
            /** @var Transaction $deal */
            $deal = $deals->get($row->ref('con_id'));
            $document = $this->dealDocument($deal, (int) $row->ref('legal_id'), $perDealDocuments, $row);
            if ($document === null) {
                continue;
            }

            $assetTypeText = Str::lower((string) $row->text('asset_type'));
            $assetType = $assetTypes->first(fn (AssetType $a) => Str::lower($a->name) === $assetTypeText);
            $nature = $this->natureFromType((int) $row->getAttribute('form_type'))
                ?? $this->natureFromType((int) ($docTypes[$row->ref('legal_id') ?? 0] ?? 0))
                ?? ($assetTypeText !== '' ? (str_contains($assetTypeText, 'immovable') ? SecurityNature::Mortgage : SecurityNature::Hypothecation) : SecurityNature::Other);

            [$idType, $idNumber] = $this->ownerId($row);
            $chargeText = Str::lower(str_replace('_', ' ', (string) $row->text('charge_type')));
            $encumbered = Str::lower(str_replace([' ', '-'], '', (string) $row->text('encumbered')));
            $owner = $row->text('asset_owner') ?? $row->text('pertaining_to');
            if ($owner === null) {
                $this->report->warn('securities', $row->id, 'No asset owner in Stack; recorded as "Not recorded".');
            }

            $values = [
                'transaction_id' => $deal->id,
                'deal_document_id' => $document->id,
                'nature' => $nature,
                'asset_owner' => mb_substr($owner ?? 'Not recorded', 0, 255),
                'owner_id_type' => $idType,
                'owner_id_number' => $idNumber,
                'asset_type_id' => $assetType?->id,
                'charge_type_id' => $chargeText !== '' ? ($chargeTypes[$chargeText] ?? null) : null,
                'pertaining_to' => $row->text('pertaining_to') ? mb_substr((string) $row->text('pertaining_to'), 0, 255) : null,
                'is_encumbered' => match ($encumbered) {
                    'encumbered' => true,
                    'nonencumbered' => false,
                    default => null,
                },
                'description' => $row->text('asset_office'),
                'address' => ($address = collect([$row->text('street_name'), $row->text('area')])->filter()->implode(', ')) !== '' ? mb_substr($address, 0, 500) : null,
                'pincode' => preg_match('/^[1-9][0-9]{5}$/', (string) $row->text('pincode')) ? $row->text('pincode') : null,
                'city' => $row->text('city') ? mb_substr((string) $row->text('city'), 0, 120) : null,
                'state_id' => $row->text('state') ? ($states[Str::lower((string) $row->text('state'))] ?? null) : null,
                'form_of_securities' => $row->text('form_of_securities') ? mb_substr((string) $row->text('form_of_securities'), 0, 255) : null,
                'confirming_party' => $row->text('confirming_party') ? mb_substr((string) $row->text('confirming_party'), 0, 255) : null,
                'created_by' => $this->idFor(User::class, $row->ref('created_by') ?? $row->ref('updated_by')) ?? $this->importUser,
                'created_at' => $row->date('created_at') ?? $row->date('created_date') ?? $deal->created_at,
                'updated_at' => $row->date('updated_at') ?? $row->date('updated_date') ?? $deal->created_at,
            ];
            if ($chargeText !== '' && $values['charge_type_id'] === null) {
                $this->report->warn('securities', $row->id, "Charge \"{$row->text('charge_type')}\" isn't in the charge types; left empty.");
            }

            /** @var DealSecurity $security */
            $security = $this->upsert(DealSecurity::class, (int) $row->id, $values, 'securities');
            $this->securityByDetail[(int) $row->id] = (int) $security->id;

            // Securities it's over: Stack's security mapping, or (pledges) the security named on the row.
            $typeIds = [];
            foreach ($mappings->get($row->id, collect()) as $map) {
                if ((int) $map->getAttribute('is_active') === 1 && (int) $map->getAttribute('is_deleted') === 0) {
                    $typeId = $this->idFor(SecurityType::class, $map->ref('security_id'));
                    if ($typeId) {
                        $typeIds[] = $typeId;
                    }
                }
                $this->securityByMapping[(int) $map->id] = (int) $security->id;
            }
            if ($typeIds === [] && $row->text('type')) {
                $typeId = $securityTypes[Str::lower((string) $row->text('type'))] ?? null;
                if ($typeId) {
                    $typeIds[] = $typeId;
                }
            }
            $security->securityTypes()->sync(array_values(array_unique($typeIds)));
        }
    }

    /**
     * Stack's CIN/PAN on a security: type 2 = CIN, 3 = PAN; kept only when the number is valid.
     *
     * @return array{0: OwnerIdType|null, 1: string|null}
     */
    private function ownerId(LegacyRecord $row): array
    {
        $number = IndianIdentifiers::normalise($row->text('cin_pan_num'));
        if ($number === null) {
            return [null, null];
        }
        if (IndianIdentifiers::isCin($number) || IndianIdentifiers::isLlpin($number)) {
            return [OwnerIdType::Cin, $number];
        }
        if (IndianIdentifiers::isPan($number)) {
            return [OwnerIdType::Pan, $number];
        }
        $this->report->warn('securities', $row->id, "\"{$number}\" isn't a valid CIN or PAN; left empty.");

        return [null, null];
    }

    /**
     * @param  array<int, int>  $perDealDocuments  Stack legal document id => deal document id
     */
    private function dealDocument(Transaction $deal, int $stackDocId, array $perDealDocuments, LegacyRecord $row): ?DealDocument
    {
        if (isset($perDealDocuments[$stackDocId])) {
            $document = DealDocument::withTrashed()->find($perDealDocuments[$stackDocId]);
            if ($document && $document->transaction_id === $deal->id) {
                return $document;
            }
        }
        $type = LegalDocumentType::withTrashed()->where('legacy_id', $stackDocId)->first();
        if ($type === null) {
            $this->report->reject('securities', $row->id, "Its document (Stack #{$stackDocId}) isn't in the legal document master.");

            return null;
        }
        $key = ['transaction_id' => $deal->id, 'legal_document_type_id' => $type->id, 'kind' => DealDocumentKind::Standard, 'sequence' => 0];
        $document = DealDocument::withTrashed()->where($key)->first();
        if ($document === null) {
            $document = (new DealDocument)->forceFill([...$key, 'name' => $type->name, 'created_by' => $this->importUser]);
            $document->save();
            $this->report->warn('securities', $row->id, "{$type->name} wasn't on the deal's documentation in Stack; added there.");
        }

        return $document;
    }

    // ------------------------------------------------------------------ registrations

    /**
     * @param  Collection<int|string, Transaction>  $deals  by legacy id
     */
    private function registrations(Collection $deals): void
    {
        $mappings = LegacyRecord::from('sec_roc_mapping')->whereIn('con_id', $deals->keys()->all())->orderBy('id')->get();
        $tables = [1 => 'sec_roc_asset_type', 2 => 'sec_cersai_asset_type', 3 => 'sec_pledge_asset_type'];
        $assetRows = collect($tables)->map(fn (string $table) => LegacyRecord::from($table)->whereIn('map_id', $mappings->pluck('id'))->where('is_active', 1)->orderBy('id')->get()->groupBy('map_id'));
        $fileColumns = ['upload_id', 'challan_id', 'sign_id', 'ack_id', 'upload_pmr_file_id', 'modify_signed_roc_id', 'modify_challan_id',
            'modify_certificate_id', 'modify_lender_noc_id', 'modify_ack_id', 'satisfy_signed_roc_id', 'satisfy_challan_id',
            'satisfy_certificate_id', 'satisfy_lender_noc_id', 'satisfy_ack_id'];
        $this->uploads = $this->uploadRows($assetRows->flatten(2)->flatMap(fn (LegacyRecord $r) => array_map(fn (string $c) => $r->ref($c), $fileColumns)));

        foreach ($mappings as $map) {
            $this->report->read('registrations');
            $kind = match ((int) $map->getAttribute('section')) {
                1 => RegistrationKind::Roc,
                2 => RegistrationKind::Cersai,
                3 => RegistrationKind::Pledge,
                default => null,
            };
            /** @var Transaction $deal */
            $deal = $deals->get($map->ref('con_id'));
            /** @var Collection<int, LegacyRecord> $rows */
            $rows = $kind ? $assetRows[(int) $map->getAttribute('section')]->get($map->id, collect()) : collect();
            if ($kind === null || (int) $map->getAttribute('is_active') !== 1 || $rows->isEmpty()) {
                $this->report->reject('registrations', $map->id, $kind === null ? 'Unknown registration kind.' : ((int) $map->getAttribute('is_active') !== 1 ? 'Removed in Stack.' : 'No filing recorded in Stack.'));

                continue;
            }

            $securityIds = collect($map->idList('security_map_id'))
                ->map(fn (int $id) => $this->securityByMapping[$id] ?? $this->securityByDetail[$id] ?? null)
                ->filter()->unique()->values()->all();
            if ($securityIds === []) {
                $this->report->reject('registrations', $map->id, 'None of its securities were imported.');

                continue;
            }

            $kind === RegistrationKind::Pledge
                ? $this->pledge($deal, $map, $rows, $securityIds)
                : $this->filing($deal, $kind, $map, $rows, $securityIds);
        }
    }

    /**
     * A ROC charge or CERSAI registration: the first row registers it, later rows modify it, and a
     * recorded satisfaction (status 2 or a satisfaction date) satisfies it.
     *
     * @param  Collection<int, LegacyRecord>  $rows
     * @param  list<int>  $securityIds
     */
    private function filing(Transaction $deal, RegistrationKind $kind, LegacyRecord $map, Collection $rows, array $securityIds): void
    {
        $first = $rows->first();
        $roc = $kind === RegistrationKind::Roc;
        $satisfied = $rows->contains(fn (LegacyRecord $r) => (int) $r->getAttribute('status') === 2 || $r->date('satisfaction_date') !== null);

        $registration = $this->registration($deal, $kind, $map, [
            'reference' => $roc ? $first->text('charge_id') : ($first->text('si_id') ?? $first->text('asset_id')),
            'amount' => $this->money($rows->last()?->text('amount')),
            'status' => $satisfied ? RegistrationStatus::Satisfied : RegistrationStatus::Active,
        ], $securityIds);

        foreach ($rows->values() as $i => $row) {
            $action = $i === 0 ? RegistrationAction::Create : RegistrationAction::Modify;
            $files = $action === RegistrationAction::Create
                ? [$row->ref('upload_id'), $row->ref('challan_id'), $row->ref('sign_id'), $row->ref('ack_id')]
                : [$row->ref('modify_signed_roc_id'), $row->ref('modify_challan_id'), $row->ref('modify_certificate_id'), $row->ref('modify_lender_noc_id'), $row->ref('modify_ack_id')];
            $this->event($registration, $deal, "{$kind->value}:{$row->id}", $action, $row->date('challan_date') ?? $row->date('created_date'),
                $roc ? $row->text('srn_no') : $row->text('transaction_id'), $this->money($row->text('amount')),
                $action === RegistrationAction::Modify ? ($row->text('modify_reason') ?? $row->text('reson_modify_satisfy') ?? 'Modified in Stack') : $row->text('remark'),
                $files, $row);
        }

        if ($satisfied) {
            $last = $rows->last();
            $this->event($registration, $deal, "{$kind->value}:{$last->id}:satisfy", RegistrationAction::Satisfy,
                $last->date('satisfaction_date') ?? $last->date('updated_date') ?? $last->date('challan_date'), null, null,
                $last->text('reson_modify_satisfy'),
                [$last->ref('satisfy_signed_roc_id'), $last->ref('satisfy_challan_id'), $last->ref('satisfy_certificate_id'), $last->ref('satisfy_lender_noc_id'), $last->ref('satisfy_ack_id')], $last);
        }
    }

    /**
     * A pledge: pledge rows add securities, unpledge rows release them; fully released = released.
     *
     * @param  Collection<int, LegacyRecord>  $rows
     * @param  list<int>  $securityIds
     */
    private function pledge(Transaction $deal, LegacyRecord $map, Collection $rows, array $securityIds): void
    {
        $first = $rows->first();
        $outstanding = 0;
        foreach ($rows as $row) {
            $quantity = (int) $row->getAttribute('number_of_securities');
            $outstanding += (int) $row->getAttribute('pledge_unpledge') === 2 ? -$quantity : $quantity;
        }

        $registration = $this->registration($deal, RegistrationKind::Pledge, $map, [
            'reference' => $first->text('isin'),
            'status' => $outstanding <= 0 ? RegistrationStatus::Satisfied : RegistrationStatus::Active,
            'security_name' => mb_substr((string) ($first->text('security_name') ?? $first->text('type') ?? 'Securities'), 0, 255),
            'quantity' => max($outstanding, 0) ?: (int) $first->getAttribute('number_of_securities'),
            'face_value' => $this->money($first->text('face_value_per_security')),
            'depository' => in_array(Str::upper((string) $first->text('depository')), ['NSDL', 'CDSL'], true) ? Str::upper((string) $first->text('depository')) : null,
            'pledgor_dp_id' => $first->text('pledgors_dp_id') ? mb_substr((string) $first->text('pledgors_dp_id'), 0, 20) : null,
            'pledgor_client_id' => $first->text('pledgors_client_id') ? mb_substr((string) $first->text('pledgors_client_id'), 0, 20) : null,
            'pledgee_dp_id' => ($first->text('pledgee_dp_id') ?? $first->text('beacons_dp_id')) ? mb_substr((string) ($first->text('pledgee_dp_id') ?? $first->text('beacons_dp_id')), 0, 20) : null,
            'pledgee_client_id' => $first->text('pledgee_client_id') ? mb_substr((string) $first->text('pledgee_client_id'), 0, 20) : null,
        ], $securityIds);

        $running = 0;
        foreach ($rows->values() as $i => $row) {
            $quantity = (int) $row->getAttribute('number_of_securities');
            $release = (int) $row->getAttribute('pledge_unpledge') === 2;
            $running += $release ? -$quantity : $quantity;
            $action = match (true) {
                $i === 0 => RegistrationAction::Create,
                $release && $running <= 0 => RegistrationAction::Satisfy,
                default => RegistrationAction::Modify,
            };
            $reason = match (true) {
                $action === RegistrationAction::Create => null,
                $release => "{$quantity} securities released".($running > 0 ? ", {$running} still pledged" : ''),
                default => "{$quantity} more securities pledged",
            };
            $this->event($registration, $deal, "pledge:{$row->id}", $action, $row->date('pledge_unpledge_date') ?? $row->date('created_date'), null, null, $reason, [$row->ref('upload_pmr_file_id')], $row);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<int>  $securityIds
     */
    private function registration(Transaction $deal, RegistrationKind $kind, LegacyRecord $map, array $values, array $securityIds): SecurityRegistration
    {
        $registration = SecurityRegistration::query()->where('legacy_key', "map:{$map->id}")->first() ?? new SecurityRegistration;
        $registration->forceFill([
            ...$values,
            'legacy_key' => "map:{$map->id}",
            'transaction_id' => $deal->id,
            'kind' => $kind,
            'created_by' => $this->idFor(User::class, $map->ref('created_by')) ?? $this->importUser,
            'created_at' => $registration->created_at ?? $map->date('created_date') ?? $deal->created_at,
        ]);
        $this->save($registration, 'registrations');
        $registration->securities()->sync($securityIds);

        return $registration;
    }

    /**
     * @param  list<int|null>  $fileIds
     */
    private function event(SecurityRegistration $registration, Transaction $deal, string $key, RegistrationAction $action, ?string $on, ?string $filing, ?string $amount, ?string $reason, array $fileIds, LegacyRecord $row): void
    {
        $this->report->read('registration filings');
        $event = SecurityRegistrationEvent::query()->where('legacy_key', $key)->first() ?? new SecurityRegistrationEvent;
        $event->forceFill([
            'legacy_key' => $key,
            'security_registration_id' => $registration->id,
            'action' => $action,
            'happened_on' => substr((string) ($on ?? $deal->created_at?->toDateString()), 0, 10),
            'filing_reference' => $filing ? mb_substr($filing, 0, 60) : null,
            'amount' => $amount,
            'reason' => $reason,
            'created_by' => $this->idFor(User::class, $row->ref('updated_by') ?? $row->ref('created_by')) ?? $this->importUser,
            'created_at' => $event->created_at ?? $row->date('created_date') ?? $deal->created_at,
        ]);
        $this->save($event, 'registration filings');

        foreach (array_unique(array_filter($fileIds)) as $uploadId) {
            $this->file($event, $deal, 'registrations', (int) $uploadId, null, null, 'registration files');
        }
    }

    // ------------------------------------------------------------------ due diligence

    /**
     * @param  Collection<int|string, Transaction>  $deals  by legacy id
     */
    private function diligence(Collection $deals): void
    {
        $legacyIds = $deals->keys()->all();
        $from = fn (string $table) => LegacyRecord::from($table)->whereIn('con_id', $legacyIds)->orderBy('id')->get();
        $uploads = $from('due_dilligience_upload_files');
        $this->uploads = $this->uploadRows($uploads->pluck('upload_id'));
        $owners = LegacyRecord::from('executed_asset_owner_data')->whereIn('con_id', $legacyIds)->pluck('asset_owner_name', 'id');

        $add = function (string $key, Transaction $deal, DiligenceKind $kind, LegacyRecord $row, array $values, Collection $files): void {
            $this->report->read('due diligence items');
            if (! $row->isLegacyActive()) {
                $this->report->reject('due diligence items', $key, 'Removed in Stack.');

                return;
            }
            $live = $files->filter(fn (LegacyRecord $f) => $f->isLegacyActive());
            $verified = $live->first(fn (LegacyRecord $f) => (int) $f->getAttribute('is_verified') === 1);
            $latest = $live->last();
            $status = match (true) {
                $verified !== null => ConditionStatus::Verified,
                $latest !== null => ConditionStatus::Submitted,
                default => ConditionStatus::Pending,
            };

            $item = DealDiligenceItem::query()->where('legacy_key', $key)->first() ?? new DealDiligenceItem;
            $item->forceFill([
                ...$values,
                'legacy_key' => $key,
                'transaction_id' => $deal->id,
                'kind' => $kind,
                'title' => mb_substr((string) ($values['title'] ?? $kind->label()), 0, 500),
                'status' => $status,
                'submitted_by' => $latest ? ($this->idFor(User::class, $latest->ref('created_by')) ?? $this->importUser) : null,
                'submitted_at' => $latest?->date('created_at'),
                'checker_id' => $verified ? ($this->idFor(User::class, $verified->ref('verified_by')) ?? $this->importUser) : null,
                'checked_at' => $verified?->date('verified_at'),
                'created_by' => $this->idFor(User::class, $row->ref('created_by')) ?? $this->importUser,
                'created_at' => $item->created_at ?? $row->date('created_at') ?? $deal->created_at,
            ]);
            $this->save($item, 'due diligence items');

            foreach ($files as $file) {
                $this->report->read('due diligence files');
                $this->file($item, $deal, 'diligence', (int) $file->ref('upload_id'), $file->isLegacyActive() ? null : ($file->date('updated_at') ?? $file->date('created_at') ?? $deal->created_at?->toDateTimeString()), $file->ref('created_by'), 'due diligence files');
            }
        };

        $filesOf = fn (int $type, ?int $con, ?callable $match = null) => $uploads
            ->filter(fn (LegacyRecord $f) => (int) $f->getAttribute('type') === $type && $f->ref('con_id') === $con && ($match === null || $match($f)))
            ->values();
        $security = fn (LegacyRecord $row) => $this->securityByMapping[$row->ref('security_mapping_id') ?? 0] ?? $this->securityByDetail[$row->ref('legal_id') ?? 0] ?? null;

        foreach ($from('due_dilligience_roc_search_data') as $row) {
            $deal = $deals->get($row->ref('con_id'));
            $add("roc_search:{$row->id}", $deal, DiligenceKind::RocSearch, $row, [
                'title' => $row->text('document'),
                'asset_owner' => mb_substr((string) ($owners[$row->ref('executed_asset_owner_id') ?? 0] ?? 'Not recorded'), 0, 255),
                'empanelled_agency_id' => $this->idFor(EmpanelledAgency::class, $row->ref('issuing_authority_id')),
            ], $filesOf(1, $row->ref('con_id'), fn ($f) => $f->ref('executed_asset_owner_id') === $row->ref('executed_asset_owner_id')));
        }
        foreach ($from('due_dilligience_document_security_data') as $row) {
            $deal = $deals->get($row->ref('con_id'));
            $add("security_certificate:{$row->id}", $deal, DiligenceKind::SecurityCertificate, $row, [
                'title' => trim('Security certificate'.($row->text('security') ? ': '.$row->text('security') : '')),
                'deal_security_id' => $security($row),
                'empanelled_agency_id' => $this->idFor(EmpanelledAgency::class, $row->ref('issuing_authority_id')),
                'reference' => $row->text('udin_unique_no') ? mb_substr((string) $row->text('udin_unique_no'), 0, 60) : null,
            ], $filesOf(2, $row->ref('con_id'), fn ($f) => ($row->ref('security_mapping_id') && $f->ref('security_mapping_id') === $row->ref('security_mapping_id')) || ($f->ref('legal_id') && $f->ref('legal_id') === $row->ref('legal_id'))));
        }
        foreach ($from('due_dilligience_noc_document_data') as $row) {
            $deal = $deals->get($row->ref('con_id'));
            $add("noc:{$row->id}", $deal, DiligenceKind::Noc, $row, [
                'title' => trim('NOC'.($row->text('charge_holder') ? ' from '.$row->text('charge_holder') : '')),
                'deal_security_id' => $security($row),
                'reference' => $row->text('charge_id') ? mb_substr((string) $row->text('charge_id'), 0, 60) : null,
            ], $filesOf(3, $row->ref('con_id'), fn ($f) => ($row->ref('security_mapping_id') && $f->ref('security_mapping_id') === $row->ref('security_mapping_id')) || ($f->ref('legal_id') && $f->ref('legal_id') === $row->ref('legal_id'))));
        }
        foreach ($from('due_dilligience_security_certificate') as $row) {
            $deal = $deals->get($row->ref('con_id'));
            $add("security_cover:{$row->id}", $deal, DiligenceKind::SecurityCover, $row, [
                'title' => $row->text('document'),
                'empanelled_agency_id' => $this->idFor(EmpanelledAgency::class, $row->ref('issuing_authority_id')),
                'reference' => $row->text('udin_unique_number') ? mb_substr((string) $row->text('udin_unique_number'), 0, 60) : null,
            ], $filesOf(4, $row->ref('con_id')));
        }
        foreach ($from('due_dilligience_additional_data') as $row) {
            $deal = $deals->get($row->ref('con_id'));
            $add("additional:{$row->id}", $deal, DiligenceKind::Other, $row, [
                'title' => $row->text('document') ?? $row->text('description'),
            ], $filesOf(5, $row->ref('con_id'), fn ($f) => $f->ref('additional_data_id') === (int) $row->id));
        }
        // Annexures: Stack keeps the files per deal, so they go with the deal's first annexure of each kind.
        $seenAnnexure = [];
        foreach ($from('due_dilligience_annexure_data') as $row) {
            $deal = $deals->get($row->ref('con_id'));
            $type = (int) $row->getAttribute('annexure_type') === 2 ? 7 : 6;
            $firstOfKind = ! isset($seenAnnexure["{$deal->id}:{$type}"]) && $row->isLegacyActive();
            if ($firstOfKind) {
                $seenAnnexure["{$deal->id}:{$type}"] = true;
            }
            $add("annexure:{$row->id}", $deal, $type === 7 ? DiligenceKind::AnnexureB : DiligenceKind::AnnexureA, $row, [
                'title' => $row->text('annexure_name'),
            ], $firstOfKind ? $filesOf($type, $row->ref('con_id')) : collect());
        }
    }

    // ------------------------------------------------------------------ shared

    private function money(?string $value): ?string
    {
        $clean = preg_replace('/[^0-9.]/', '', (string) $value);

        return $clean !== '' && is_numeric($clean) && (float) $clean < 1e16 ? number_format((float) $clean, 2, '.', '') : null;
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

    /**
     * Records one Stack upload against an item or filing, and copies the file from the uploads
     * copy when one is configured (not in a dry run).
     */
    private function file(DealDiligenceItem|SecurityRegistrationEvent $owner, Transaction $deal, string $folder, int $uploadId, ?string $removedAt, ?int $uploadedBy, string $entity): void
    {
        $upload = $this->uploads[$uploadId] ?? null;
        if ($upload === null) {
            $this->report->reject($entity, $uploadId, "Upload #{$uploadId} isn't in Stack's file list.");

            return;
        }

        $key = ['attachable_type' => $owner::class, 'attachable_id' => $owner->id, 'legacy_id' => $uploadId];
        $file = DocumentFile::query()->where($key)->first() ?? (new DocumentFile)->forceFill($key);
        $file->forceFill([
            'original_name' => mb_substr($upload->text('name') ?? basename((string) $upload->text('path')) ?: 'file', 0, 255),
            'uploaded_by' => $this->idFor(User::class, $upload->ref('created_by') ?? $uploadedBy) ?? $this->importUser,
            'removed_at' => $removedAt === null ? null : ($file->removed_at ?? $removedAt),
            'removed_by' => $removedAt === null ? null : ($file->removed_by ?? $this->importUser),
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
        $path = "deals/{$deal->ulid}/{$folder}/stack-{$uploadId}".($extension !== '' ? ".{$extension}" : '');
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
