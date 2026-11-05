<?php

namespace App\Actions\Purchases;

use App\Actions\Orders\TransitionOrder;
use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\InvalidPurchaseOperation;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SendPurchaseOrder
{
    public function __construct(private TransitionOrder $transition) {}

    /**
     * Mark a draft as sent to the shop. The orders whose lines are now all on a sent purchase order
     * move to ordered.
     *
     * @throws InvalidPurchaseOperation when the purchase order is not a draft
     */
    public function handle(PurchaseOrder $purchase, ?User $user = null): void
    {
        if ($purchase->status !== PurchaseOrderStatus::Draft) {
            throw new InvalidPurchaseOperation('status', 'purchases.errors.not_draft');
        }

        DB::transaction(function () use ($purchase, $user): void {
            $purchase->forceFill(['status' => PurchaseOrderStatus::Sent, 'sent_at' => now()])->save();

            AuditLog::record('purchase_order.sent', $purchase, ['status' => 'draft'], ['status' => 'sent'], $user);

            $orders = Order::query()
                ->whereIn('id', $purchase->orderItems()->pluck('order_id'))
                ->where('status', OrderStatus::AdvanceReceived->value)
                ->with('items.purchaseOrder')
                ->get();

            foreach ($orders as $order) {
                if ($order->items->every(fn ($item): bool => $item->purchaseOrder?->status->isPlaced() ?? false)) {
                    $this->transition->handle($order, OrderStatus::Ordered, null, $user);
                }
            }
        });
    }
}
