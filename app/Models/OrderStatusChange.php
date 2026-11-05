<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderStatusChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One step in the life of an order.
 *
 * @property int $id
 * @property int $order_id
 * @property OrderStatus $from
 * @property OrderStatus $to
 * @property string|null $reason
 * @property int|null $user_id
 * @property Carbon|null $created_at
 */
#[Fillable(['order_id', 'from', 'to', 'reason', 'user_id'])]
class OrderStatusChange extends Model
{
    /** @use HasFactory<OrderStatusChangeFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['from' => OrderStatus::class, 'to' => OrderStatus::class];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
