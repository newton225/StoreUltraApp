<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Support\Search;
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
 * @property bool $bought_whole Whether a product with components is bought ready-made (it then shows itself on the purchase order).
 * @property bool $sellable Whether the product can be put in a kit (false: only a component or bought for the stock).
 * @property int $pack_size Units sold by the shop in one package (1 when sold one by one).
 * @property Carbon|null $archived_at
 */
#[Fillable(['name', 'unit', 'shop_id', 'pack_size', 'sellable', 'bought_whole'])]
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
        return ['archived_at' => 'datetime', 'pack_size' => 'integer', 'sellable' => 'boolean', 'bought_whole' => 'boolean'];
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
     * What is in stock for the product (no row until the first movement).
     *
     * @return HasOne<ProductStock, $this>
     */
    public function stock(): HasOne
    {
        return $this->hasOne(ProductStock::class);
    }

    /**
     * What a manufactured product is made of. Empty for a product that is bought as it is.
     *
     * @return HasMany<ProductComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(ProductComponent::class)->orderBy('id');
    }

    /**
     * Whether the product is made of other products and not bought ready-made: then it is not bought itself, its
     * components are. A product with components that is bought whole is bought like any other.
     */
    public function isManufactured(): bool
    {
        return ! $this->bought_whole && $this->components->isNotEmpty();
    }

    /**
     * The products that are bought as such: all but those made of components that are not bought whole.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeBoughtAsSuch(Builder $query): Builder
    {
        return $query->where(fn (Builder $inner) => $inner->where('bought_whole', true)->orWhereDoesntHave('components'));
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
        return $query->where(function (Builder $inner) use ($term): void {
            Search::contains($inner, 'name', $term);
            $inner->orWhereHas('shop', fn (Builder $shops) => Search::contains($shops, 'name', $term));
        });
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
