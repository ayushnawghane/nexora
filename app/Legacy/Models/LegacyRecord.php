<?php

namespace App\Legacy\Models;

use Illuminate\Database\Eloquent\Builder;

/** Any simple legacy table (the small masters), read through the same read-only base. */
class LegacyRecord extends LegacyModel
{
    /**
     * @return Builder<self>
     */
    public static function from(string $table): Builder
    {
        return (new self)->setTable($table)->newQuery();
    }
}
