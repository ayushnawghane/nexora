<?php

namespace App\Legacy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * One import area. Every imported row keeps its legacy id in `legacy_id`, and rows are matched on
 * it, so running an area again updates what changed instead of duplicating anything.
 */
abstract class Importer
{
    protected ImportReport $report;

    /** @var array<class-string<Model>, array<int, int>> legacy id => Nexora id, per model */
    private array $ids = [];

    /** @var array<class-string<Model>, true> models whose ids have been read from the database */
    private array $loaded = [];

    abstract public function area(): string;

    /** One line for the command's help: what this area brings in. */
    abstract public function description(): string;

    abstract protected function import(): void;

    public function run(ImportReport $report): void
    {
        $this->report = $report;
        $this->import();
    }

    /**
     * Creates or updates the Nexora row for a legacy row (matched on legacy_id) and reports which.
     * Values are set with forceFill, so columns outside $fillable (status, timestamps) can be carried over.
     *
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $attributes
     * @return T
     */
    protected function upsert(string $class, int $legacyId, array $attributes, string $entity): Model
    {
        /** @var T $model */
        $model = $this->query($class)->where('legacy_id', $legacyId)->first() ?? new $class;

        $model->forceFill(['legacy_id' => $legacyId, ...$attributes]);
        $outcome = ! $model->exists ? 'created' : ($model->isDirty() ? 'updated' : 'unchanged');
        if ($outcome !== 'unchanged') {
            $model->save();
        }

        $this->report->saved($entity, $outcome);
        $this->ids[$class][$legacyId] = (int) $model->getKey();

        return $model;
    }

    /**
     * Links a Nexora row created before the import (e.g. the seeded DEB product or a user added by
     * hand) to its legacy row, when one matches on $column and isn't linked to anything yet.
     *
     * @param  class-string<Model>  $class
     */
    protected function claim(string $class, int $legacyId, string $column, mixed $value): void
    {
        if ($value === null || $class::query()->where('legacy_id', $legacyId)->exists()) {
            return;
        }

        $match = $class::query()->whereNull('legacy_id')->where($column, $value)->first();
        if ($match) {
            $match->forceFill(['legacy_id' => $legacyId])->save();
        }
    }

    /**
     * The Nexora id of an already-imported legacy row, or null when it wasn't imported.
     *
     * @param  class-string<Model>  $class
     */
    protected function idFor(string $class, ?int $legacyId): ?int
    {
        if ($legacyId === null) {
            return null;
        }
        if (! isset($this->loaded[$class])) {
            $this->loaded[$class] = true;
            $this->ids[$class] = ($this->ids[$class] ?? []) + $this->query($class)
                ->whereNotNull('legacy_id')->pluck('id', 'legacy_id')->map(fn ($id) => (int) $id)->all();
        }

        return $this->ids[$class][$legacyId]
            ?? LegacyAlias::query()->where('model', $class)->where('legacy_id', $legacyId)->value('target_id');
    }

    /**
     * Records that a legacy row was merged into an existing Nexora record, so later lookups of its
     * legacy id resolve to that record.
     *
     * @param  class-string<Model>  $class
     */
    protected function alias(string $class, int $legacyId, int $targetId, string $reason, string $entity): void
    {
        $alias = LegacyAlias::query()->firstOrNew(['model' => $class, 'legacy_id' => $legacyId]);
        $alias->fill(['target_id' => $targetId, 'reason' => $reason]);
        $outcome = ! $alias->exists ? 'created' : ($alias->isDirty() ? 'updated' : 'unchanged');
        $alias->save();
        $this->report->saved($entity, $outcome);
    }

    /**
     * A query that also sees soft-deleted rows, so a re-run finds records deleted in Nexora too.
     *
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @return Builder<T>
     */
    private function query(string $class): Builder
    {
        return $class::query()->withoutGlobalScope(SoftDeletingScope::class);
    }
}
