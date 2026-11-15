<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A reduction granted on the price of an order, with its reason. Never modified or removed: a wrong discount
 * is not corrected, the order is cancelled and made again.
 *
 * @property int $id
 * @property int $order_id
 * @property int $amount
 * @property string $reason
 * @property int $total_before
 * @property int $total_after
 * @property int|null $user_id
 * @property Carbon|null $created_at
 */
#[Fillable(['order_id', 'amount', 'reason', 'total_before', 'total_after', 'user_id'])]
class OrderDiscount extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
        static::deleting(fn (): bool => false);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'total_before' => 'integer', 'total_after' => 'integer'];
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
