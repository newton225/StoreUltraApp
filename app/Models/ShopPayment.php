<?php

namespace App\Models;

use Database\Factories\ShopPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money paid to a shop (or given back by it). Never edited or deleted.
 *
 * @property int $id
 * @property int $shop_id
 * @property int|null $purchase_order_id
 * @property string $kind payment or refund
 * @property int $amount
 * @property int $payment_method_id
 * @property string|null $reference
 * @property Carbon $paid_on
 * @property int|null $paid_by
 * @property string|null $notes
 */
#[Fillable(['shop_id', 'purchase_order_id', 'kind', 'amount', 'payment_method_id', 'reference', 'paid_on', 'paid_by', 'notes'])]
class ShopPayment extends Model
{
    /** @use HasFactory<ShopPaymentFactory> */
    use HasFactory;

    public const KIND_PAYMENT = 'payment';

    public const KIND_REFUND = 'refund';

    /**
     * A payment to a shop is never modified or removed once recorded.
     */
    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
        static::deleting(fn (): bool => false);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_on' => 'date'];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
