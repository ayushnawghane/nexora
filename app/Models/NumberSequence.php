<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $key
 * @property int $last_value
 */
class NumberSequence extends Model
{
    protected $fillable = ['key', 'last_value'];

    protected function casts(): array
    {
        return ['last_value' => 'integer'];
    }

    /**
     * Takes the next number for a key. The row is locked until the surrounding transaction
     * commits, so two requests can never get the same number; if that transaction rolls back,
     * the number is released and reused (no gaps).
     */
    public static function next(string $key): int
    {
        return DB::transaction(function () use ($key) {
            static::query()->firstOrCreate(['key' => $key], ['last_value' => 0]);

            $sequence = static::query()->where('key', $key)->lockForUpdate()->firstOrFail();
            $sequence->increment('last_value');

            return $sequence->last_value;
        });
    }
}
