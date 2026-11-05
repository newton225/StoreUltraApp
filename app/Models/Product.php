<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A product of the catalog. Its prices live in the price history.
 *
 * @property int $id
 * @property string $name
 * @property string $unit
 * @property int|null $shop_id
 * @property Carbon|null $archived_at
 */
#[Fillable(['name', 'unit', 'shop_id'])]
class Product extends Model
{
    use Audited;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Every price the product ever had, newest first.
     *
     * @return HasMany<ProductPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class)
            ->orderByDesc('effective_from')
            ->orderByDesc('id');
    }

    /**
     * The latest price already in effect.
     *
     * @return HasOne<ProductPrice, $this>
     */
    public function currentPrice(): HasOne
    {
        return $this->hasOne(ProductPrice::class)->ofMany(
            ['effective_from' => 'max', 'id' => 'max'],
            fn (Builder $query) => $query->whereDate('effective_from', '<=', today()),
        );
    }

    /**
     * The columns a list of products can be sorted by (the keys of the column headers).
     */
    public const SORTABLE = ['name', 'shop'];

    /**
     * Sorts a list of products by one of the SORTABLE keys, by name when none is given.
     *
     * @param  Builder<Product>  $query
     * @param  'asc'|'desc'  $direction
     * @return Builder<Product>
     */
    public function scopeSortedBy(Builder $query, ?string $sort, string $direction = 'asc'): Builder
    {
        return (match ($sort) {
            'shop' => $query->orderBy(Shop::query()->select('name')->whereColumn('shops.id', 'products.shop_id'), $direction),
            default => $query->orderBy('name', $sort === 'name' ? $direction : 'asc'),
        })->orderBy('id');
    }

    /**
     * Match a term against the name of the product and the name of its shop.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn (Builder $inner) => $inner
            ->whereLike('name', $like)
            ->orWhereHas('shop', fn (Builder $shops) => $shops->whereLike('name', $like)));
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
