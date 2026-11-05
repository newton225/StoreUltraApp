<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\OrderPricesMissing;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\KitVersion;
use App\Models\Order;
use App\Models\SupplyPlan;
use App\Models\User;
use App\Support\KitPricing;
use App\Support\WeekCalendar;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CreateOrder
{
    public function __construct(private KitPricing $pricing, private WeekCalendar $calendar) {}

    /**
     * Create an order and freeze the quantities and prices of the kit version at this moment.
     *
     * @throws OrderPricesMissing when a price needed to cost or sell the kit is unknown
     */
    public function handle(
        Client $client,
        KitVersion $version,
        int $kitsCount,
        CarbonInterface $deliveryWeek,
        int $advanceAmount,
        ?SupplyPlan $plan = null,
        ?User $author = null,
        ?string $notes = null,
    ): Order {
        $summary = $this->pricing->summary($version);

        if (! $summary->isComplete()) {
            throw OrderPricesMissing::forKit($version->id);
        }

        $totals = $summary->forKits($kitsCount);

        return DB::transaction(function () use ($client, $version, $kitsCount, $deliveryWeek, $advanceAmount, $plan, $author, $notes, $totals): Order {
            $order = Order::create([
                'client_id' => $client->id,
                'supply_plan_id' => $plan?->id,
                'kit_version_id' => $version->id,
                'kits_count' => $kitsCount,
                'delivery_week' => $this->calendar->startOf($deliveryWeek)->toDateString(),
                'status' => OrderStatus::Draft->value,
                'advance_due' => $advanceAmount,
                'total_sale' => $totals['sale'],
                'total_purchase_expected' => $totals['purchase'],
                'notes' => $notes,
                'created_by' => $author?->id,
            ]);

            $order->forceFill(['reference' => sprintf('ORD-%06d', $order->id)])->save();

            foreach ($version->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'shop_id' => $item->product->shop_id,
                    'quantity' => $item->quantity_per_kit * $kitsCount,
                    'unit_purchase_price' => $item->product->currentPrice->purchase_price,
                    'line_sale_total' => $item->sale_price_per_kit === null ? null : $item->sale_price_per_kit * $kitsCount,
                ]);
            }

            AuditLog::record('order.created', $order, [], [
                'client_id' => $client->id,
                'kits_count' => $kitsCount,
                'delivery_week' => $order->delivery_week->toDateString(),
                'total_sale' => $totals['sale'],
                'total_purchase_expected' => $totals['purchase'],
            ], $author);

            return $order;
        });
    }
}
