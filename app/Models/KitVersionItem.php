<?php

namespace App\Models;

use Database\Factories\KitVersionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One product of a kit version with its quantity and the sale price of the whole line.
 *
 * @property int $id
 * @property int $kit_version_id
 * @property int $product_id
 * @property int $quantity_per_kit
 * @property int|null $sale_price_per_kit
 */
#[Fillable(['kit_version_id', 'product_id', 'quantity_per_kit', 'sale_price_per_kit'])]
class KitVersionItem extends Model
{
    /** @use HasFactory<KitVersionItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_per_kit' => 'integer',
            'sale_price_per_kit' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<KitVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(KitVersion::class, 'kit_version_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
