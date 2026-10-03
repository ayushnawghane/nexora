<?php

namespace App\Legacy;

/**
 * What an import run did, per kind of record: how many rows were read, created, updated or left
 * unchanged, which were rejected and why, and anything adjusted on the way in (warnings).
 */
class ImportReport
{
    /** @var array<string, array{read: int, created: int, updated: int, unchanged: int, rejected: int}> */
    private array $counts = [];

    /** @var list<array{entity: string, legacy_id: int|string|null, reason: string}> */
    private array $rejections = [];

    /** @var list<array{entity: string, legacy_id: int|string|null, note: string}> */
    private array $warnings = [];

    /** @var array<string, string> */
    private array $totals = [];

    public function __construct(public readonly string $area, public readonly bool $dryRun) {}

    public function read(string $entity, int $count = 1): void
    {
        $this->bump($entity, 'read', $count);
    }

    /** @param 'created'|'updated'|'unchanged' $outcome */
    public function saved(string $entity, string $outcome): void
    {
        $this->bump($entity, $outcome);
    }

    public function reject(string $entity, int|string|null $legacyId, string $reason): void
    {
        $this->bump($entity, 'rejected');
        $this->rejections[] = ['entity' => $entity, 'legacy_id' => $legacyId, 'reason' => $reason];
    }

    public function warn(string $entity, int|string|null $legacyId, string $note): void
    {
        $this->warnings[] = ['entity' => $entity, 'legacy_id' => $legacyId, 'note' => $note];
    }

    /** A reconciliation figure, e.g. the sum of fees in legacy and in Nexora. */
    public function total(string $label, string $value): void
    {
        $this->totals[$label] = $value;
    }

    /**
     * @return array<string, array{read: int, created: int, updated: int, unchanged: int, rejected: int}>
     */
    public function counts(): array
    {
        return $this->counts;
    }

    /**
     * @return list<array{entity: string, legacy_id: int|string|null, reason: string}>
     */
    public function rejections(): array
    {
        return $this->rejections;
    }

    /**
     * @return list<array{entity: string, legacy_id: int|string|null, note: string}>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return array<string, string>
     */
    public function totals(): array
    {
        return $this->totals;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'area' => $this->area,
            'dry_run' => $this->dryRun,
            'counts' => $this->counts,
            'totals' => $this->totals,
            'rejections' => $this->rejections,
            'warnings' => $this->warnings,
        ];
    }

    private function bump(string $entity, string $key, int $by = 1): void
    {
        $this->counts[$entity] ??= ['read' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'rejected' => 0];
        $this->counts[$entity][$key] += $by;
    }
}
