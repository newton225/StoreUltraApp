<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\ProductComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One component of a manufactured product: how much of another product goes into one unit of it.
 *
 * @property int $id
 * @property int $product_id The manufactured product.
 * @property int $component_product_id The product it is made of.
 * @property float $quantity Quantity of the component in one unit of the manufactured product.
 */
#[Fillable(['product_id', 'component_product_id', 'quantity'])]
class ProductComponent extends Model
{
    use Audited;

    /** @use HasFactory<ProductComponentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'float'];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}
