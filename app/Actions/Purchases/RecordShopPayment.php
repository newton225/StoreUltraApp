<?php

namespace App\Actions\Purchases;

use App\Exceptions\InvalidPayment;
use App\Models\AuditLog;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\ShopPayment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RecordShopPayment
{
    /**
     * Record money paid to a shop, or given back by it (never more than what was paid).
     *
     * @throws InvalidPayment when the refund is more than what was paid
     */
    public function handle(
        Shop $shop,
        string $kind,
        int $amount,
        PaymentMethod $method,
        ?string $reference,
        CarbonInterface $paidOn,
        ?User $user = null,
        ?PurchaseOrder $purchase = null,
        ?string $notes = null,
    ): ShopPayment {
        return DB::transaction(function () use ($shop, $kind, $amount, $method, $reference, $paidOn, $user, $purchase, $notes): ShopPayment {
            if ($kind === ShopPayment::KIND_REFUND && $amount > $shop->netPaid()) {
                throw new InvalidPayment('amount', 'purchases.errors.refund_too_high');
            }

            $payment = ShopPayment::create([
                'shop_id' => $shop->id,
                'purchase_order_id' => $purchase?->id,
                'kind' => $kind,
                'amount' => $amount,
                'payment_method_id' => $method->id,
                'reference' => $reference,
                'paid_on' => $paidOn->toDateString(),
                'paid_by' => $user?->id,
                'notes' => $notes,
            ]);

            AuditLog::record('shop_payment.recorded', $payment, [], [
                'shop_id' => $shop->id,
                'purchase_order_id' => $purchase?->id,
                'kind' => $kind,
                'amount' => $amount,
                'method' => $method->code,
            ], $user);

            return $payment;
        });
    }
}
