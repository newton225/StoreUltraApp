<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * What is bought from one shop for the deliveries of one week.
 *
 * @property int $id
 * @property string|null $reference
 * @property int $shop_id
 * @property Carbon $week
 * @property PurchaseOrderStatus $status
 * @property int $expected_total
 * @property int $actual_total
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $sent_at
 */
#[Fillable(['reference', 'shop_id', 'week', 'status', 'expected_total', 'actual_total', 'notes', 'created_by', 'sent_at'])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week' => 'date',
            'status' => PurchaseOrderStatus::class,
            'expected_total' => 'integer',
            'actual_total' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('id');
    }

    /**
     * The order lines this purchase order covers.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<ShopPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(ShopPayment::class);
    }

    /**
     * The columns the list of purchase orders can be sorted by (the keys of the column headers).
     */
    public const SORTABLE = ['reference', 'shop', 'status', 'expected', 'actual'];

    /**
     * Sorts a list of purchase orders by one of the SORTABLE keys, by shop when none is given.
     *
     * @param  Builder<PurchaseOrder>  $query
     * @param  'asc'|'desc'  $direction
     * @return Builder<PurchaseOrder>
     */
    public function scopeSortedBy(Builder $query, ?string $sort, string $direction = 'asc'): Builder
    {
        return (match ($sort) {
            'reference' => $query->orderBy('reference', $direction),
            'shop' => $query->orderBy(Shop::query()->select('name')->whereColumn('shops.id', 'purchase_orders.shop_id'), $direction),
            'status' => $query->orderBy('status', $direction),
            'expected' => $query->orderBy('expected_total', $direction),
            'actual' => $query->orderBy('actual_total', $direction),
            default => $query->orderBy('shop_id'),
        })->orderBy('id', $sort === null ? 'asc' : $direction);
    }
}
