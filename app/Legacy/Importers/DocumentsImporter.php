<?php

namespace App\Legacy\Importers;

use App\Enums\ConditionStage;
use App\Enums\ConditionStatus;
use App\Enums\DealDocumentKind;
use App\Enums\LegalDocumentCategory;
use App\Legacy\Importer;
use App\Legacy\Models\LegacyRecord;
use App\Models\ConditionDocument;
use App\Models\DealCondition;
use App\Models\DealDocument;
use App\Models\DocumentFile;
use App\Models\IssuingAuthority;
use App\Models\LegalDocumentType;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Documentation of the imported DT deals: issuing authorities, the legal document and CP/CS masters,
 * each deal's legal documents (with the execution versions uploaded in Stack) and its CP/CS items
 * (with their files). Needs the transactions area first.
 *
 * - Stack keeps a deal's supplements, amendments and further copies as extra rows of its legal
 *   document master; here they're deal documents of the right kind, numbered as Stack named them.
 * - About 22,000 CP rows came from Stack's older ERP and only carry the document's name. They're
 *   matched to the CP/CS master by name; anything without a match becomes an item for that deal only.
 *   Stack's master repeats some names, so a deal can list the same item twice; the repeat is merged
 *   into the first (its files move over, and the further-along status wins).
 * - A legal document Stack created for one deal only (not a supplement of a master document) comes
 *   in under the "Other document" type, keeping its Stack name.
 * - Stack's statuses: pending → pending, WIP/completed → waiting for a check, verified → verified.
 * - Files arrive as records ("not copied yet") and are copied from LEGACY_UPLOADS_PATH when it's set,
 *   the same folder the letter PDFs come from.
 */
class DocumentsImporter extends Importer
{
    private const PRODUCT = 4;

    private int $importUser;

    /** @var array<string, int> lower-case authority name => Nexora id */
    private array $authorityByName = [];

    /** @var array<string, array<string, int>> stage => lower-case master name => Nexora id */
    private array $conditionByName = [];

    /** @var array<string, array<int, int>> stage => Stack master id => Nexora id (merged duplicates included) */
    private array $conditionIds = [];

    /** @var array<int, int> Stack global legal document id => Nexora type id */
    private array $typeIds = [];

    /** @var array<int, string> Nexora type id => name */
    private array $typeNames = [];

    /** @var array<int, LegacyRecord> upload_file rows by id, for the area being imported */
    private array $uploads = [];

    public function area(): string
    {
        return 'documents';
    }

    public function description(): string
    {
        return 'Issuing authorities, legal document and CP/CS masters; DT deals\' legal documents and CP/CS items with their files';
    }

    protected function import(): void
    {
        $user = $this->importUser();
        if ($user === null) {
            return;
        }
        $this->importUser = $user;

        $this->authorities();
        $this->legalDocumentTypes();
        $this->conditionMasters(ConditionStage::Precedent, 'cp_documents', 'cp_doc_name', 'cp_mapping', 'cp_doc_id');
        $this->conditionMasters(ConditionStage::Subsequent, 'cs_document', 'cs_doc_name', 'cs_mapping', 'cs_doc_id');

        $dealIds = LegacyRecord::from('transaction')->where('product_id', self::PRODUCT)->where('is_deleted', 0)->pluck('id')->all();
        $deals = Transaction::query()->whereIn('legacy_id', $dealIds)->get(['id', 'ulid', 'legacy_id', 'created_at'])->keyBy('legacy_id');

        $this->dealDocuments($deals);
        $this->conditions($deals, ConditionStage::Precedent, 'pre_documents_data', 'cp_document_id', 'pre');
        $this->conditions($deals, ConditionStage::Subsequent, 'post_documents_data', 'cs_document_id', 'post');
    }

    private function authorities(): void
    {
        foreach (LegacyRecord::from('issuing_authority')->orderBy('id')->get() as $row) {
            $this->report->read('issuing authorities');
            $name = $row->text('Issuer_name');
            if ($name === null) {
                $this->report->reject('issuing authorities', $row->id, 'No name.');

                continue;
            }
            $key = Str::lower($name);
            if (isset($this->authorityByName[$key])) {
                $this->alias(IssuingAuthority::class, (int) $row->id, $this->authorityByName[$key], "Same name as another authority: {$name}", 'issuing authorities');

                continue;
            }
            $this->claim(IssuingAuthority::class, (int) $row->id, 'name', $name);
            $authority = $this->upsert(IssuingAuthority::class, (int) $row->id, [
                'name' => $name,
                'is_active' => $row->isLegacyActive(),
            ], 'issuing authorities');
            $this->authorityByName[$key] = (int) $authority->id;
        }
    }

    /**
     * Stack's global legal documents (con_id 0, no parent) become the legal document master.
     */
    private function legalDocumentTypes(): void
    {
        $byName = [];
        $rows = LegacyRecord::from('master_legal_documents')->where('con_id', 0)->where('parent_id', 0)->orderBy('id')->get();

        foreach ($rows as $row) {
            $this->report->read('legal documents');
            $name = $row->text('legal_document_name');
            if ($name === null) {
                $this->report->reject('legal documents', $row->id, 'No name.');

                continue;
            }
            $key = Str::lower($name);
            if (isset($byName[$key])) {
                $this->alias(LegalDocumentType::class, (int) $row->id, $byName[$key], "Same name as another document: {$name}", 'legal documents');
                $this->typeIds[(int) $row->id] = $byName[$key];

                continue;
            }

            $category = match ((int) $row->getAttribute('doc_category')) {
                1 => LegalDocumentCategory::Transaction,
                2 => LegalDocumentCategory::Security,
                3 => LegalDocumentCategory::Undertaking,
                default => null,
            };
            if ($category === null) {
                $this->report->warn('legal documents', $row->id, "{$name}: no category in Stack; filed as a transaction document.");
                $category = LegalDocumentCategory::Transaction;
            }

            $this->claim(LegalDocumentType::class, (int) $row->id, 'name', $name);
            /** @var LegalDocumentType $type */
            $type = $this->upsert(LegalDocumentType::class, (int) $row->id, [
                'name' => $name,
                'category' => $category,
                'is_active' => $row->isLegacyActive(),
            ], 'legal documents');

            $products = array_values(array_filter(array_map(fn (int $id) => $this->idFor(Product::class, $id), $row->idList('product_id'))));
            $type->products()->sync($products);

            $byName[$key] = $this->typeIds[(int) $row->id] = (int) $type->id;
            $this->typeNames[(int) $type->id] = $name;
        }
    }

    private function conditionMasters(ConditionStage $stage, string $table, string $nameColumn, string $mappingTable, string $mappingKey): void
    {
        $entity = "{$stage->short()} documents";
        $mapping = LegacyRecord::from($mappingTable)->where('is_deleted', 0)->get()->keyBy($mappingKey);
        $this->conditionByName[$stage->value] = [];
        $this->conditionIds[$stage->value] = [];
        $byName = &$this->conditionByName[$stage->value];

        $rows = LegacyRecord::from($table)->where(fn ($q) => $q->whereNull('con_id')->orWhere('con_id', 0))->orderBy('id')->get();
        foreach ($rows as $row) {
            $this->report->read($entity);
            $name = $row->text($nameColumn);
            if ($name === null) {
                $this->report->reject($entity, $row->id, 'No name.');

                continue;
            }
            $key = Str::lower($name);
            if (isset($byName[$key])) {
                $this->report->warn($entity, $row->id, "Same name as another {$stage->short()} document; merged: {$name}");
                $this->conditionIds[$stage->value][(int) $row->id] = $byName[$key];

                continue;
            }

            /** @var LegacyRecord|null $flags */
            $flags = $mapping->get($row->id);
            $flag = fn (string $column) => (int) ($flags?->getAttribute($column) ?? 0) === 1;

            $document = $this->upsertCondition(ConditionDocument::class, $stage, (int) $row->id, [
                'stage' => $stage,
                'name' => $name,
                'issuing_authority_id' => $this->idFor(IssuingAuthority::class, $row->ref('issuing_authority_id')),
                'listed_secured' => $flag('listed_secured'),
                'listed_unsecured' => $flag('listed_unsecured'),
                'unlisted_secured' => $flag('unlisted_secured'),
                'unlisted_unsecured' => $flag('unlisted_unsecured'),
                'is_active' => $row->isLegacyActive(),
            ], $entity);
            $byName[$key] = $this->conditionIds[$stage->value][(int) $row->id] = (int) $document->id;
        }
    }

    /**
     * Legal documents per deal: the standard ones that had an upload in Stack, plus every supplement,
     * amendment and further copy Stack added to the deal.
     *
     * @param  Collection<int|string, Transaction>  $deals  by legacy id
     */
    private function dealDocuments(Collection $deals): void
    {
        $legacyIds = $deals->keys()->all();
        $perDeal = LegacyRecord::from('master_legal_documents')->whereIn('con_id', $legacyIds)->orderBy('id')->get()->groupBy('con_id');
        $mappings = LegacyRecord::from('upload_document_mapping')->whereIn('con_id', $legacyIds)->orderBy('id')->get()->groupBy('con_id');
        $this->uploads = $this->uploadRows(LegacyRecord::from('upload_document_mapping')->whereIn('con_id', $legacyIds)->pluck('upload_id'));

        foreach ($deals as $legacyId => $deal) {
            $documents = []; // Stack legal document id => DealDocument

            foreach ($perDeal->get($legacyId, collect()) as $row) {
                $this->report->read('deal documents');
                $document = $this->extraDocument($deal, $row);
                if ($document) {
                    $documents[(int) $row->id] = $document;
                }
            }

            foreach ($mappings->get($legacyId, collect())->groupBy('legal_id') as $stackDocId => $rows) {
                $document = $documents[(int) $stackDocId] ?? null;
                if ($document === null && isset($this->typeIds[(int) $stackDocId])) {
                    $this->report->read('deal documents');
                    $document = $documents[(int) $stackDocId] = $this->standardDocument($deal, $this->typeIds[(int) $stackDocId], $rows->first());
                }
                if ($document === null) {
                    foreach ($rows as $row) {
                        $this->report->reject('legal document files', $row->id, "The document it belongs to (Stack #{$stackDocId}) wasn't imported.");
                    }

                    continue;
                }
                $this->documentFiles($document, $deal, $rows);
            }
        }
    }

    /** The legal document type for Stack's one-off, deal-only documents. */
    private function otherType(): int
    {
        /** @var LegalDocumentType $type */
        $type = LegalDocumentType::withTrashed()->firstOrCreate(['name' => 'Other document'], ['category' => LegalDocumentCategory::Transaction]);
        $product = $this->idFor(Product::class, self::PRODUCT);
        if ($product) {
            $type->products()->syncWithoutDetaching([$product]);
        }
        $this->typeNames[(int) $type->id] = $type->name;

        return (int) $type->id;
    }

    private function standardDocument(Transaction $deal, int $typeId, LegacyRecord $first): DealDocument
    {
        $key = [
            'transaction_id' => $deal->id,
            'legal_document_type_id' => $typeId,
            'kind' => DealDocumentKind::Standard,
            'sequence' => 0,
        ];
        $document = DealDocument::withTrashed()->where($key)->first() ?? (new DealDocument)->forceFill($key);
        $document->forceFill([
            'name' => $this->typeNames[$typeId] ?? LegalDocumentType::withTrashed()->whereKey($typeId)->value('name'),
            'created_by' => $document->created_by ?? $this->idFor(User::class, $first->ref('created_by')) ?? $this->importUser,
            'created_at' => $document->created_at ?? $first->date('created_at') ?? $deal->created_at,
        ]);
        $this->save($document, 'deal documents');

        return $document;
    }

    /**
     * A supplement, amendment or further copy Stack added to the deal ("Supplement Deed of
     * Hypothecation-2"), numbered as Stack named it.
     */
    private function extraDocument(Transaction $deal, LegacyRecord $row): ?DealDocument
    {
        $name = $row->text('legal_document_name');
        if ($name === null) {
            $this->report->reject('deal documents', $row->id, 'No name.');

            return null;
        }
        $typeId = $this->typeIds[$row->ref('parent_id') ?? 0] ?? $this->typeIds[$row->ref('supplementary_to') ?? 0] ?? null;
        if ($typeId === null) {
            $typeId = $this->otherType();
            $this->report->warn('deal documents', $row->id, "{$name}: made for this deal only in Stack; filed under \"Other document\".");
        }
        if ($row->isLegacyDeleted()) {
            $this->report->reject('deal documents', $row->id, "{$name}: deleted in Stack.");

            return null;
        }

        $kind = match (true) {
            Str::startsWith(Str::lower($name), 'supplement') => DealDocumentKind::Supplement,
            Str::startsWith(Str::lower($name), 'amendment') => DealDocumentKind::Amendment,
            default => DealDocumentKind::Additional,
        };
        $sequence = preg_match('/-(\d+)\s*$/', $name, $m) ? max(1, (int) $m[1]) : 1;

        $existing = DealDocument::withTrashed()->where('legacy_id', $row->id)->first();
        $taken = DealDocument::withTrashed()->where('transaction_id', $deal->id)->where('legal_document_type_id', $typeId)
            ->where('kind', $kind)->when($existing, fn ($q) => $q->whereKeyNot($existing->id))->pluck('sequence')->all();
        if (in_array($sequence, $taken, true) && $existing && ! in_array($existing->sequence, $taken, true)) {
            $sequence = $existing->sequence; // renumbered on an earlier run: keep that number
        } elseif (in_array($sequence, $taken, true)) {
            $next = max($taken) + 1;
            $this->report->warn('deal documents', $row->id, "{$name}: number {$sequence} is used twice on the deal in Stack; numbered {$next}.");
            $sequence = $next;
        }

        return $this->upsert(DealDocument::class, (int) $row->id, [
            'transaction_id' => $deal->id,
            'legal_document_type_id' => $typeId,
            'kind' => $kind,
            'sequence' => $sequence,
            'name' => $name,
            'created_by' => $this->idFor(User::class, $row->ref('created_by')) ?? $this->importUser,
            'created_at' => $row->date('created_at') ?? $deal->created_at,
            'updated_at' => $row->date('updated_at') ?? $row->date('created_at') ?? $deal->created_at,
        ], 'deal documents');
    }

    /**
     * Stack's upload mappings for one document: the newest live one is the current file, the rest
     * are history.
     *
     * @param  Collection<int, LegacyRecord>  $rows
     */
    private function documentFiles(DealDocument $document, Transaction $deal, Collection $rows): void
    {
        $live = $rows->filter(fn (LegacyRecord $r) => $r->isLegacyActive());
        $current = $live->last();
        if ($live->count() > 1) {
            $this->report->warn('legal document files', $current?->id, "{$document->name}: {$live->count()} live uploads in Stack; the newest is current, the others are history.");
        }

        foreach ($rows as $row) {
            $this->report->read('legal document files');
            $removed = $row !== $current;
            $this->file($document, $deal, 'documents', $row->ref('upload_id'), (int) $row->id, $removed ? ($row->date('updated_at') ?? $row->date('created_at')) : null, $row->ref('updated_by'), 'legal document files', $row->ref('created_by'));
        }
    }

    /**
     * CP or CS items of every deal.
     *
     * @param  Collection<int|string, Transaction>  $deals  by legacy id
     */
    private function conditions(Collection $deals, ConditionStage $stage, string $table, string $masterColumn, string $section): void
    {
        $entity = "{$stage->short()} items";
        $fileEntity = "{$stage->short()} files";
        $rows = LegacyRecord::from($table)->whereIn('con_id', $deals->keys()->all())->orderBy('id')->get();
        $maps = LegacyRecord::from('pre_post_upload_map')->where('section', $section)->whereIn('id', $rows->pluck('id'))->get()->groupBy('id');
        $uploadIds = $maps->flatten()->pluck('upload_id')
            ->merge($rows->flatMap(fn (LegacyRecord $r) => $r->idList('upload_id')))->unique()->values();
        $this->uploads = $this->uploadRows($uploadIds);

        // First pass: read every row and group the ones that are the same item on the same deal.
        $groups = [];   // list of lists of row entries
        $groupOf = [];  // "deal|name:<lower name>" / "deal|master:<id>" => group index
        foreach ($rows as $row) {
            $this->report->read($entity);
            /** @var Transaction $deal */
            $deal = $deals->get($row->ref('con_id'));
            $name = $row->text('document_name');
            if ($name === null) {
                $this->report->reject($entity, $row->id, 'No document name.');

                continue;
            }
            if ((int) $row->getAttribute('is_active') !== 1) {
                $this->report->reject($entity, $row->id, "{$name}: removed in Stack.");

                continue;
            }

            $masterId = $this->conditionIds[$stage->value][$row->ref($masterColumn) ?? 0]
                ?? $this->conditionByName[$stage->value][Str::lower($name)]
                ?? null;
            $keys = array_filter(["{$deal->id}|name:".Str::lower($name), $masterId ? "{$deal->id}|master:{$masterId}" : null]);
            $index = null;
            foreach ($keys as $key) {
                $index ??= $groupOf[$key] ?? null;
            }
            if ($index === null) {
                $index = count($groups);
                $groups[] = [];
            } else {
                $this->report->warn($entity, $row->id, "{$name}: on the deal twice in Stack; merged into one item.");
            }
            foreach ($keys as $key) {
                $groupOf[$key] ??= $index;
            }
            $groups[$index][] = $this->conditionEntry($row, $deal, $name, $masterId, $maps, $entity);
        }

        // Second pass: one item per group, saved once: the first row's details, the further-along
        // status of the group, and every row's files.
        $rank = fn (ConditionStatus $s) => match ($s) {
            ConditionStatus::Verified => 3,
            ConditionStatus::Submitted => 2,
            default => 1,
        };
        foreach ($groups as $entries) {
            $first = $entries[0];
            $best = collect($entries)->sortByDesc(fn (array $e) => $rank($e['progress']['status']))->first();
            /** @var LegacyRecord $row */
            $row = $first['row'];
            $deal = $first['deal'];
            $fallback = $deal->created_at?->toDateTimeString();

            /** @var DealCondition $condition */
            $condition = $this->upsertCondition(DealCondition::class, $stage, (int) $row->id, [
                'transaction_id' => $deal->id,
                'stage' => $stage,
                'condition_document_id' => $first['master_id'],
                'name' => $first['name'],
                'issuing_authority_id' => $first['authority_id'],
                ...$best['progress'],
                'created_by' => $this->idFor(User::class, $row->ref('created_by')) ?? $this->importUser,
                'created_at' => $row->date('created_date') ?? $fallback,
                'updated_at' => $row->date('updated_at') ?? $row->date('created_date') ?? $fallback,
            ], $entity);

            foreach ($entries as $entry) {
                $this->conditionFiles($condition, $deal, $entry['files'], $entry['row'], $fileEntity);
            }
        }
    }

    /**
     * What one Stack CP/CS row says: its files, status and who did what.
     *
     * @param  Collection<array-key, mixed>  $maps  pre_post_upload_map rows by item id
     * @return array{row: LegacyRecord, deal: Transaction, name: string, master_id: int|null, authority_id: int|null, files: Collection<int, array{upload_id: int, active: bool}>, progress: array<string, mixed>}
     */
    private function conditionEntry(LegacyRecord $row, Transaction $deal, string $name, ?int $masterId, Collection $maps, string $entity): array
    {
        /** @var Collection<int, LegacyRecord> $mapped */
        $mapped = $maps->get($row->id, collect());
        $files = $mapped->map(fn (LegacyRecord $m) => ['upload_id' => (int) $m->getAttribute('upload_id'), 'active' => (int) $m->getAttribute('is_active') === 1])->values();
        if ($files->isEmpty()) {
            // Older rows list their uploads only in the comma-separated column.
            $files = collect($row->idList('upload_id'))->map(fn (int $id) => ['upload_id' => $id, 'active' => true])->values();
        }
        $hasLiveFiles = $files->contains(fn (array $f) => $f['active']);

        $status = match ((string) $row->getAttribute('status')) {
            'verified' => ConditionStatus::Verified,
            'WIP', 'completed' => ConditionStatus::Submitted,
            default => ConditionStatus::Pending,
        };
        if ($status === ConditionStatus::Submitted && ! $hasLiveFiles) {
            $this->report->warn($entity, $row->id, "{$name}: marked WIP in Stack with no files; imported as pending.");
            $status = ConditionStatus::Pending;
        }
        if ($status === ConditionStatus::Pending && $hasLiveFiles) {
            $status = ConditionStatus::Submitted;
        }

        $fallback = $deal->created_at?->toDateTimeString();
        $maker = $this->idFor(User::class, $row->ref('updated_by') ?? $row->ref('created_by')) ?? $this->importUser;

        return [
            'row' => $row,
            'deal' => $deal,
            'name' => $name,
            'master_id' => $masterId,
            'authority_id' => $this->idFor(IssuingAuthority::class, $row->ref('issuing_authority_id'))
                ?? ($row->text('issuer_name') ? ($this->authorityByName[Str::lower((string) $row->text('issuer_name'))] ?? null) : null),
            'files' => $files,
            'progress' => [
                'status' => $status,
                'submitted_by' => $status === ConditionStatus::Pending ? null : $maker,
                'submitted_at' => $status === ConditionStatus::Pending ? null : ($row->date('updated_at') ?? $row->date('created_date') ?? $fallback),
                'checker_id' => $status === ConditionStatus::Verified ? ($this->idFor(User::class, $row->ref('verified_by')) ?? $this->importUser) : null,
                'checked_at' => $status === ConditionStatus::Verified ? ($row->date('verified_date') ?? $row->date('updated_at') ?? $fallback) : null,
            ],
        ];
    }

    /**
     * @param  Collection<int, array{upload_id: int, active: bool}>  $files
     */
    private function conditionFiles(DealCondition $condition, Transaction $deal, Collection $files, LegacyRecord $row, string $entity): void
    {
        foreach ($files as $file) {
            $this->report->read($entity);
            $removedAt = $file['active'] ? null : ($row->date('updated_at') ?? $deal->created_at?->toDateTimeString());
            $this->file($condition, $deal, 'conditions', $file['upload_id'], $file['upload_id'], $removedAt, null, $entity, $row->ref('updated_by') ?? $row->ref('created_by'));
        }
    }

    /**
     * Records one Stack upload against a document or CP/CS item (matched on the upload id, so a
     * re-run updates it), and copies the file over when a copy of Stack's uploads folder is set.
     */
    private function file(DealDocument|DealCondition $owner, Transaction $deal, string $folder, ?int $uploadId, int $sourceId, ?string $removedAt, ?int $removedBy, string $entity, ?int $uploadedBy = null): void
    {
        $upload = $this->uploads[$uploadId ?? 0] ?? null;
        if ($upload === null) {
            $this->report->reject($entity, $sourceId, "Upload #{$uploadId} isn't in Stack's file list.");

            return;
        }

        $key = ['attachable_type' => $owner::class, 'attachable_id' => $owner->id, 'legacy_id' => (int) $upload->id];
        $file = DocumentFile::query()->where($key)->first() ?? (new DocumentFile)->forceFill($key);
        $file->forceFill([
            'original_name' => mb_substr($upload->text('name') ?? basename((string) $upload->text('path')) ?: 'file', 0, 255),
            'uploaded_by' => $this->idFor(User::class, $upload->ref('created_by') ?? $uploadedBy) ?? $this->importUser,
            'removed_at' => $removedAt === null ? null : ($file->removed_at ?? $removedAt),
            'removed_by' => $removedAt === null ? null : ($file->removed_by ?? $this->idFor(User::class, $removedBy) ?? $this->importUser),
            'created_at' => $file->created_at ?? $upload->date('created_at') ?? $deal->created_at,
        ]);
        $this->save($file, $entity);
        $this->copy($file, $deal, $folder, $upload, $entity);
    }

    /**
     * Stack's upload_file rows for these ids, keyed by id.
     *
     * @param  Collection<array-key, mixed>  $ids
     * @return array<int, LegacyRecord>
     */
    private function uploadRows(Collection $ids): array
    {
        $rows = [];
        foreach ($ids->filter()->unique()->chunk(5000) as $chunk) {
            foreach (LegacyRecord::from('upload_file')->whereIn('id', $chunk->all())->get(['id', 'name', 'path', 'created_by', 'created_at']) as $row) {
                $rows[(int) $row->id] = $row;
            }
        }

        return $rows;
    }

    /**
     * Copies a file from the copy of Stack's uploads folder (config legacy.uploads_path), if one is
     * configured and the file is there. Skipped in a dry run: files aren't rolled back.
     */
    private function copy(DocumentFile $file, Transaction $deal, string $folder, LegacyRecord $upload, string $entity): void
    {
        $root = config('legacy.uploads_path');
        if ($file->isAvailable() || ! $root) {
            return;
        }
        $relative = ltrim(str_replace('\\', '/', (string) $upload->text('path')), '/');
        $source = rtrim((string) $root, '/\\').DIRECTORY_SEPARATOR.$relative;
        if ($relative === '' || str_contains($relative, '..') || ! is_file($source)) {
            $this->report->warn("{$entity} (copy)", $upload->id, "Not found in the uploads copy: {$relative}");

            return;
        }
        if ($this->report->dryRun) {
            $this->report->saved("{$entity} (copy)", 'created');

            return;
        }

        $extension = Str::lower(pathinfo($relative, PATHINFO_EXTENSION));
        $path = "deals/{$deal->ulid}/{$folder}/stack-{$upload->id}".($extension !== '' ? ".{$extension}" : '');
        Storage::disk('local')->put($path, (string) file_get_contents($source));
        $file->forceFill(['path' => $path, 'size' => filesize($source) ?: null, 'mime' => mime_content_type($source) ?: null])->save();
        $this->report->saved("{$entity} (copy)", 'created');
    }

    /**
     * Like upsert(), for the tables whose legacy ids are only unique per stage (CP and CS come from
     * two Stack tables).
     *
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $attributes
     * @return T
     */
    private function upsertCondition(string $class, ConditionStage $stage, int $legacyId, array $attributes, string $entity): Model
    {
        $query = $class === ConditionDocument::class ? ConditionDocument::withTrashed() : $class::query();
        /** @var T $model */
        $model = $query->where('stage', $stage)->where('legacy_id', $legacyId)->first() ?? new $class;
        $model->forceFill(['legacy_id' => $legacyId, ...$attributes]);
        $this->save($model, $entity);

        return $model;
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
