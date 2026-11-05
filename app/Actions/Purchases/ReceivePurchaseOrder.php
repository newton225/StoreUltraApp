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

class ReceivePurchaseOrder
{
    public function __construct(private TransitionOrder $transition) {}

    /**
     * Record the goods received so far (cumulative quantities, and the price really paid when it
     * differs). When everything has arrived, the orders whose shops have all delivered move to received.
     *
     * @param  array<int, array{id: int, quantity_received: int, unit_price_actual: int|null}>  $lines
     *
     * @throws InvalidPurchaseOperation when nothing can be received on this purchase order
     */
    public function handle(PurchaseOrder $purchase, array $lines, ?User $user = null): void
    {
        if (! $purchase->status->canReceive()) {
            throw new InvalidPurchaseOperation('status', 'purchases.errors.not_receivable');
        }

        DB::transaction(function () use ($purchase, $lines, $user): void {
            foreach ($lines as $line) {
                $purchase->items()->whereKey($line['id'])->update([
                    'quantity_received' => $line['quantity_received'],
                    'unit_price_actual' => $line['unit_price_actual'],
                ]);
            }

            $items = $purchase->items()->get();
            $complete = $items->every(fn ($item): bool => $item->quantity_received >= $item->quantity_ordered);
            $any = $items->contains(fn ($item): bool => $item->quantity_received > 0);

            $status = match (true) {
                $complete => PurchaseOrderStatus::Received,
                $any => PurchaseOrderStatus::PartiallyReceived,
                default => PurchaseOrderStatus::Sent,
            };

            $actual = $items->sum(fn ($item): int => $item->quantity_received * $item->effectivePrice());

            $purchase->forceFill(['status' => $status, 'actual_total' => $actual])->save();

            AuditLog::record('purchase_order.received', $purchase, [], [
                'status' => $status->value,
                'actual_total' => $actual,
            ], $user);

            if ($status === PurchaseOrderStatus::Received) {
                $this->advanceOrders($purchase, $user);
            }
        });
    }

    private function advanceOrders(PurchaseOrder $purchase, ?User $user): void
    {
        $orders = Order::query()
            ->whereIn('id', $purchase->orderItems()->pluck('order_id'))
            ->where('status', OrderStatus::Ordered->value)
            ->with('items.purchaseOrder')
            ->get();

        foreach ($orders as $order) {
            if ($order->items->every(fn ($item): bool => $item->purchaseOrder?->status === PurchaseOrderStatus::Received)) {
                $this->transition->handle($order, OrderStatus::Received, null, $user);
            }
        }
    }
}
