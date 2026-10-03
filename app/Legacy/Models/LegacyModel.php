<?php

namespace App\Legacy\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Base for models over the legacy Stack database (`legacy` connection). They are for reading only:
 * any attempt to write through them throws, so an import can never change the legacy data.
 */
abstract class LegacyModel extends Model
{
    protected $connection = 'legacy';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        $refuse = fn () => throw new LogicException('The legacy database is read-only.');
        static::saving($refuse);
        static::deleting($refuse);
    }

    /** Legacy rows are "deleted" by flag; is_deleted = 1 means the record was removed in Stack. */
    public function isLegacyDeleted(): bool
    {
        return (int) ($this->getAttributes()['is_deleted'] ?? 0) === 1;
    }

    /** Whether the record was active in Stack (and not deleted). */
    public function isLegacyActive(): bool
    {
        return ! $this->isLegacyDeleted() && (int) ($this->getAttributes()['is_active'] ?? 1) === 1;
    }

    /** A trimmed string, or null when blank (legacy uses '', NULL and stray whitespace interchangeably). */
    public function text(string $column): ?string
    {
        $value = $this->getAttributes()[$column] ?? null;
        if ($value === null) {
            return null;
        }
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        return $value === '' ? null : $value;
    }

    /** A legacy id column, or null for the 0 / NULL legacy uses for "none". */
    public function ref(string $column): ?int
    {
        $value = (int) ($this->getAttributes()[$column] ?? 0);

        return $value > 0 ? $value : null;
    }

    /**
     * A comma-separated id list ("2,3,4") as integers.
     *
     * @return list<int>
     */
    public function idList(string $column): array
    {
        $ids = array_map('intval', explode(',', (string) ($this->getAttributes()[$column] ?? '')));

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    /** A date or datetime, or null for empty and zero dates ('0000-00-00'). */
    public function date(string $column): ?string
    {
        $value = $this->text($column);

        return $value === null || str_starts_with($value, '0000') ? null : $value;
    }
}
