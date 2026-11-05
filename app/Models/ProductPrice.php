<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\ProductPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a product's price history. A price is never edited: a change is a new line.
 *
 * @property int $id
 * @property int $product_id
 * @property int|null $shop_id
 * @property int $purchase_price
 * @property int|null $sale_price
 * @property Carbon $effective_from
 * @property int|null $created_by
 */
#[Fillable(['product_id', 'shop_id', 'purchase_price', 'sale_price', 'effective_from', 'created_by'])]
class ProductPrice extends Model
{
    use Audited;

    /** @use HasFactory<ProductPriceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_price' => 'integer',
            'sale_price' => 'integer',
            'effective_from' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
