<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasActiveFlag
{
    public function initializeHasActiveFlag(): void
    {
        $this->mergeCasts(['is_active' => 'boolean']);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }
}
