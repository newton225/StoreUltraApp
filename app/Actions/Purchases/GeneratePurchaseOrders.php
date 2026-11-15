<?php

namespace App\Actions\Purchases;

use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\AuditLog;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\DocumentNumbers;
use App\Support\OrderNeeds;
use App\Support\PackQuantity;
use App\Support\WeekCalendar;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GeneratePurchaseOrders
{
    public function __construct(private DocumentNumbers $numbers, private WeekCalendar $calendar, private OrderNeeds $needs) {}

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

            $needs = $this->needs->forItems($items);
            $unassigned = $needs->whereNull('shop_id')->pluck('order_item_id')->unique()->count();
            $created = 0;

            foreach ($needs->whereNotNull('shop_id')->groupBy('shop_id') as $shopId => $shopNeeds) {
                $purchase = PurchaseOrder::create([
                    'reference' => $this->numbers->next('purchase_order'),
                    'shop_id' => $shopId,
                    'week' => $weekStart->toDateString(),
                    'status' => PurchaseOrderStatus::Draft,
                    'created_by' => $user?->id,
                ]);

                $expected = 0;

                $packSizes = Product::query()->whereIn('id', $shopNeeds->pluck('product_id'))->pluck('pack_size', 'id');

                foreach ($shopNeeds->groupBy('product_id') as $productId => $productNeeds) {
                    foreach ($this->purchaseLines($productNeeds, (int) ($packSizes[$productId] ?? 1)) as $line) {
                        $purchase->items()->create([
                            'product_id' => $productId,
                            'quantity_ordered' => $line['quantity'],
                            'unit_price_expected' => $line['price'],
                        ]);

                        $expected += $line['quantity'] * $line['price'];
                    }
                }

                $expected = (int) round($expected);

                $purchase->forceFill(['expected_total' => $expected])->save();

                // A manufactured line whose components come from several shops stays on the first purchase order made for it.
                OrderItem::query()->whereIn('id', $shopNeeds->pluck('order_item_id')->unique())->whereNull('purchase_order_id')->update(['purchase_order_id' => $purchase->id]);

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

    /**
     * The purchase lines of one product. Sold one by one, a line per price. Sold by package, one line
     * for the whole need rounded up to whole packages, at the price of the biggest part of the need
     * (the extra units of the last package are paid at that price).
     *
     * @param  Collection<int, array{order_item_id: int, product_id: int, shop_id: int|null, quantity: int, unit_price: int|float}>  $needs  The needs of one product.
     * @return list<array{quantity: int, price: int|float}>
     */
    private function purchaseLines(Collection $needs, int $packSize): array
    {
        $byPrice = $needs->groupBy(fn (array $need): string => (string) $need['unit_price']);

        if ($packSize <= 1) {
            return $byPrice
                ->map(fn (Collection $lines): array => ['quantity' => (int) $lines->sum('quantity'), 'price' => $lines->first()['unit_price']])
                ->values()
                ->all();
        }

        $biggest = $byPrice->sortByDesc(fn (Collection $lines): int => (int) $lines->sum('quantity'))->first();

        return [[
            'quantity' => PackQuantity::units((int) $needs->sum('quantity'), $packSize),
            'price' => $biggest->first()['unit_price'],
        ]];
    }
}
