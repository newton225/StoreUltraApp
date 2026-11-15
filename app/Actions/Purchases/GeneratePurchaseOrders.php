<?php

namespace App\Actions\Purchases;

use App\Actions\Stock\ReserveStockForNeeds;
use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\AuditLog;
use App\Models\OrderItem;
use App\Models\OrderItemNeed;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\BusinessSettings;
use App\Support\DocumentNumbers;
use App\Support\OrderNeeds;
use App\Support\PackQuantity;
use App\Support\WeekCalendar;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GeneratePurchaseOrders
{
    public function __construct(
        private DocumentNumbers $numbers,
        private WeekCalendar $calendar,
        private OrderNeeds $needs,
        private ReserveStockForNeeds $reserve,
        private BusinessSettings $settings,
    ) {}

    /**
     * Group the needs of the week into one purchase order per shop. Only orders whose advance is received are
     * taken, and a line already on a purchase order is never taken twice. What the lines need is first served from
     * the stock (the oldest order first, at the average cost); what is missing is bought in whole packages. A shop
     * with nothing to buy gets no purchase order. Needs whose product has no shop are left aside and counted.
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
                    ->whereIn('status', OrderStatus::purchasableValues((bool) $this->settings->get('purchase_on_credit')))
                    ->whereDate('delivery_week', $weekStart))
                ->orderBy('id')
                ->get();

            // A need already prepared is never made twice. One prepared earlier (when kits were delivered for a line that
            // had no purchase yet) that still waits for goods and is on no purchase order is taken up here.
            $known = OrderItemNeed::query()->whereIn('order_item_id', $items->pluck('id'))->whereNull('released_at')->get()
                ->keyBy(fn (OrderItemNeed $need): string => $need->order_item_id.'|'.$need->product_id);

            $all = $this->needs->forItems($items);
            $key = fn (array $need): string => $need['order_item_id'].'|'.$need['product_id'];
            $fresh = $all->reject(fn (array $need): bool => $known->has($key($need)));
            $pending = $all
                ->filter(fn (array $need): bool => $known->has($key($need)) && $known[$key($need)]->purchase_order_item_id === null && $known[$key($need)]->unmet() > 0)
                ->map(fn (array $need): array => [...$need, 'need' => $known[$key($need)], 'shortfall' => $known[$key($need)]->unmet()]);

            $unassigned = $fresh->whereNull('shop_id')->pluck('order_item_id')->unique()->count();
            $rows = $this->reserve->handle($fresh->whereNotNull('shop_id'), $user)->merge($pending->whereNotNull('shop_id'));
            $created = 0;

            foreach ($rows->groupBy('shop_id') as $shopId => $shopRows) {
                $toBuy = $shopRows->where('shortfall', '>', 0);

                if ($toBuy->isEmpty()) {
                    continue;
                }

                $purchase = PurchaseOrder::create([
                    'reference' => $this->numbers->next('purchase_order'),
                    'shop_id' => $shopId,
                    'week' => $weekStart->toDateString(),
                    'status' => PurchaseOrderStatus::Draft,
                    'kind' => PurchaseOrder::KIND_ORDERS,
                    'created_by' => $user?->id,
                ]);

                $expected = 0;
                $packSizes = Product::query()->whereIn('id', $toBuy->pluck('product_id'))->pluck('pack_size', 'id');

                foreach ($toBuy->groupBy('product_id') as $productId => $productRows) {
                    $lines = $this->purchaseLines(
                        $productRows->map(fn (array $row): array => ['quantity' => $row['shortfall'], 'unit_price' => $row['unit_price']]),
                        (int) ($packSizes[$productId] ?? 1),
                    );

                    $createdItems = [];

                    foreach ($lines as $line) {
                        $createdItems[(string) $line['price']] = $purchase->items()->create([
                            'product_id' => $productId,
                            'quantity_ordered' => $line['quantity'],
                            'unit_price_expected' => $line['price'],
                        ]);

                        $expected += $line['quantity'] * $line['price'];
                    }

                    foreach ($productRows as $row) {
                        $line = $createdItems[(string) $row['unit_price']] ?? reset($createdItems);
                        $row['need']->forceFill(['purchase_order_item_id' => $line->id])->save();
                    }
                }

                $expected = (int) round($expected);

                $purchase->forceFill(['expected_total' => $expected])->save();

                // A manufactured line whose components come from several shops stays on the first purchase order made for it.
                OrderItem::query()->whereIn('id', $toBuy->pluck('order_item_id')->unique())->whereNull('purchase_order_id')->update(['purchase_order_id' => $purchase->id]);

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
     * @param  Collection<int, array{quantity: int, unit_price: int|float}>  $needs  What is still to buy for one product.
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
