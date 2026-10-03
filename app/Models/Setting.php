<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * App-wide key/value settings. Keys are declared as constants here; read and write through
 * value()/put() so a missing row simply reads as null.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 */
class Setting extends Model
{
    use LogsActivity;

    /** Beacon's own GSTIN; its state code is the home state for CGST+SGST vs IGST. */
    public const BEACON_GSTIN = 'tax.beacon_gstin';

    protected $fillable = ['key', 'value'];

    public static function value(string $key): ?string
    {
        return static::query()->where('key', $key)->value('value');
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['key', 'value'])->logOnlyDirty();
    }
}
