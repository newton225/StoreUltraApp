<?php

namespace App\Actions\Purchases;

use App\Actions\Stock\AllocatePurchasedStock;
use App\Actions\Stock\RecordStockMovement;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InvalidPurchaseOperation;
use App\Models\AuditLog;
use App\Models\OrderItemNeed;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReceivePurchaseOrder
{
    public function __construct(private RecordStockMovement $stock, private AllocatePurchasedStock $purchased) {}

    /**
     * Record the goods received so far (cumulative quantities, and the price really paid when it
     * differs). What arrives enters the stock; no order moves forward because of it.
     *
     * @param  array<int, array{id: int, quantity_received: int, unit_price_actual: float|null}>  $lines
     *
     * @throws InvalidPurchaseOperation when nothing can be received on this purchase order
     */
    public function handle(PurchaseOrder $purchase, array $lines, ?User $user = null): void
    {
        if (! $purchase->status->canReceive()) {
            throw new InvalidPurchaseOperation('status', 'purchases.errors.not_receivable');
        }

        DB::transaction(function () use ($purchase, $lines, $user): void {
            $previous = $purchase->items()->pluck('quantity_received', 'id');

            foreach ($lines as $line) {
                $purchase->items()->whereKey($line['id'])->update([
                    'quantity_received' => $line['quantity_received'],
                    'unit_price_actual' => $line['unit_price_actual'],
                ]);
            }

            $items = $purchase->items()->with('product')->get();

            $this->receiveIntoStock($items, $previous, $purchase->kind, $user);

            $complete = $items->every(fn ($item): bool => $item->quantity_received >= $item->quantity_ordered);
            $any = $items->contains(fn ($item): bool => $item->quantity_received > 0);

            $status = match (true) {
                $complete => PurchaseOrderStatus::Received,
                $any => PurchaseOrderStatus::PartiallyReceived,
                default => PurchaseOrderStatus::Sent,
            };

            $actual = (int) round($items->sum(fn ($item): int|float => $item->quantity_received * $item->effectivePrice()));

            $purchase->forceFill(['status' => $status, 'actual_total' => $actual])->save();

            AuditLog::record('purchase_order.received', $purchase, [], [
                'status' => $status->value,
                'actual_total' => $actual,
            ], $user);
        });
    }

    /**
     * What was received since the last time (the quantities are cumulative) enters the stock at the price really paid
     * for the line, or the planned one. A restock order has no customer order behind it: everything received is stock.
     * On a purchase order made for customer orders, the needs waiting on the line are served from what arrived and
     * only the extra units (the rest of a whole package) stay in the stock. A line prepared before the stock existed
     * has no needs: it is left alone, its goods were never counted as stock.
     *
     * @param  Collection<int, PurchaseOrderItem>  $items
     * @param  Collection<int, int>  $previous  Quantity received before this call, by line id.
     */
    private function receiveIntoStock(Collection $items, Collection $previous, string $kind, ?User $user): void
    {
        foreach ($items as $item) {
            $delta = $item->quantity_received - (int) $previous->get($item->id, 0);

            if ($delta <= 0) {
                continue;
            }

            if ($kind === PurchaseOrder::KIND_ORDERS && ! OrderItemNeed::query()->where('purchase_order_item_id', $item->id)->exists()) {
                continue;
            }

            $price = $item->effectivePrice();

            $this->stock->handle($item->product, StockMovementType::Receipt, $delta, $price, $user, purchaseOrderItemId: $item->id);

            if ($kind === PurchaseOrder::KIND_ORDERS) {
                $this->purchased->handle($item, $delta, $price, $user);
            }
        }
    }
}
