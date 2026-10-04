<?php

namespace App\Models;

use App\Enums\LegalDocumentCategory;
use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A legal document a deal can be executed under (trust deed, deed of hypothecation …), for the
 * products it's linked to.
 *
 * @property int $id
 * @property string $name
 * @property LegalDocumentCategory $category
 * @property bool $is_active
 */
class LegalDocumentType extends Model
{
    use HasActiveFlag, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'category', 'is_active'];

    protected function casts(): array
    {
        return ['category' => LegalDocumentCategory::class];
    }

    /**
     * Active types linked to the product.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForProduct(Builder $query, int $productId): void
    {
        $query->active()->whereHas('products', fn (Builder $q) => $q->whereKey($productId));
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * @return HasMany<DealDocument, $this>
     */
    public function dealDocuments(): HasMany
    {
        return $this->hasMany(DealDocument::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
