<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $gst_code
 * @property bool $is_union_territory
 */
class State extends Model
{
    protected $fillable = ['name', 'gst_code', 'is_union_territory'];

    protected function casts(): array
    {
        return ['is_union_territory' => 'boolean'];
    }

    /**
     * @return HasMany<Pincode, $this>
     */
    public function pincodes(): HasMany
    {
        return $this->hasMany(Pincode::class);
    }
}
