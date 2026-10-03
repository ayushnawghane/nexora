<?php

namespace App\Legacy;

use Illuminate\Database\Eloquent\Model;

/**
 * A legacy row that was merged into another Nexora record during import.
 *
 * @property int $id
 * @property string $model
 * @property int $legacy_id
 * @property int $target_id
 * @property string $reason
 */
class LegacyAlias extends Model
{
    protected $fillable = ['model', 'legacy_id', 'target_id', 'reason'];

    protected function casts(): array
    {
        return ['legacy_id' => 'integer', 'target_id' => 'integer'];
    }
}
