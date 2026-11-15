<?php

namespace App\Models;

use App\Casts\Amount;
use App\Enums\StockMovementType;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of the stock ledger. Never edited or deleted: a mistake is corrected by another movement.
 *
 * @property int $id
 * @property int $product_id
 * @property StockMovementType $type
 * @property int $quantity Signed: positive grows the stock, negative shrinks it.
 * @property int|float $unit_cost Cost of one unit at the time of the movement.
 * @property int|null $order_item_id
 * @property int|null $purchase_order_item_id
 * @property string|null $reason
 * @property int|null $user_id
 * @property Carbon $created_at
 */
#[Fillable(['product_id', 'type', 'quantity', 'unit_cost', 'order_item_id', 'purchase_order_item_id', 'reason', 'user_id'])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * A movement is never modified or removed once recorded.
     */
    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
        static::deleting(fn (): bool => false);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'integer',
            'unit_cost' => Amount::class,
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
