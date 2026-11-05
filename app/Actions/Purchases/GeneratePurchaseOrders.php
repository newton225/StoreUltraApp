<?php

namespace App\Actions\Purchases;

use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\AuditLog;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\DocumentNumbers;
use App\Support\WeekCalendar;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class GeneratePurchaseOrders
{
    public function __construct(private DocumentNumbers $numbers, private WeekCalendar $calendar) {}

    /**
     * Group the needs of the week into one purchase order per shop. Only orders whose advance is
     * received are taken, and a line already on a purchase order is never taken twice. Lines whose
     * product has no shop are left aside and counted.
     *
     * @return array{created: int, unassigned: int}
     */
    public function handle(CarbonInterface $week, ?User $user = null): array
    {
        $weekStart = $this->calendar->startOf($week);

        return DB::transaction(function () use ($weekStart, $user): array {
            $items = OrderItem::query()
                ->whereNull('purchase_order_id')
                ->whereHas('order', fn ($orders) => $orders
                    ->where('status', OrderStatus::AdvanceReceived->value)
                    ->whereDate('delivery_week', $weekStart))
                ->orderBy('id')
                ->get();

            $unassigned = $items->whereNull('shop_id')->count();
            $created = 0;

            foreach ($items->whereNotNull('shop_id')->groupBy('shop_id') as $shopId => $shopItems) {
                $purchase = PurchaseOrder::create([
                    'reference' => $this->numbers->next('purchase_order'),
                    'shop_id' => $shopId,
                    'week' => $weekStart->toDateString(),
                    'status' => PurchaseOrderStatus::Draft,
                    'created_by' => $user?->id,
                ]);

                $expected = 0;

                foreach ($shopItems->groupBy(fn (OrderItem $item): string => $item->product_id.'|'.$item->unit_purchase_price) as $lines) {
                    $quantity = (int) $lines->sum('quantity');
                    $price = $lines->first()->unit_purchase_price;

                    $purchase->items()->create([
                        'product_id' => $lines->first()->product_id,
                        'quantity_ordered' => $quantity,
                        'unit_price_expected' => $price,
                    ]);

                    $expected += $quantity * $price;
                }

                $purchase->forceFill(['expected_total' => $expected])->save();

                OrderItem::query()->whereIn('id', $shopItems->pluck('id'))->update(['purchase_order_id' => $purchase->id]);

                AuditLog::record('purchase_order.created', $purchase, [], [
                    'reference' => $purchase->reference,
                    'shop_id' => $shopId,
                    'week' => $weekStart->toDateString(),
                    'expected_total' => $expected,
                ], $user);

                $created++;
            }

            return ['created' => $created, 'unassigned' => $unassigned];
        });
    }
}
