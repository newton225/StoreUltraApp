<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

class ProfitReport
{
    public const GROUPS = ['week', 'zone', 'client', 'kit'];

    private const SUMS = [
        'sale', 'collected', 'theoretical_purchase', 'theoretical_margin',
        'actual_purchase', 'logistics', 'real_margin', 'projected_margin', 'gap',
    ];

    public function __construct(private OrderProfit $profit) {}

    /**
     * The theoretical and real profit of the orders of a period, in total and grouped.
     *
     * @param  array{from: string, to: string, zone: int|null, scope: string, group: string}  $filters
     * @return array{totals: array<string, int>, rows: array<int, array<string, int|string>>}
     */
    public function build(array $filters): array
    {
        $orders = Order::query()
            ->with(['client.zone', 'kitVersion.kit'])
            ->whereDate('delivery_week', '>=', $filters['from'])
            ->whereDate('delivery_week', '<=', $filters['to'])
            ->when(
                $filters['scope'] === 'all',
                fn (Builder $query) => $query->where('status', '!=', OrderStatus::Cancelled->value),
                fn (Builder $query) => $query->whereIn('status', [OrderStatus::Settled->value, OrderStatus::Closed->value]),
            )
            ->when($filters['zone'] !== null, fn (Builder $query) => $query->whereHas('client', fn ($clients) => $clients->where('zone_id', $filters['zone'])))
            ->orderBy('delivery_week')
            ->orderBy('id')
            ->get();

        $profits = $this->profit->forOrders($orders);

        $totals = $this->blank();
        $groups = [];

        foreach ($orders as $order) {
            $label = $this->label($filters['group'], $order);
            $groups[$label] ??= $this->blank();

            foreach (self::SUMS as $key) {
                $groups[$label][$key] += $profits[$order->id][$key];
                $totals[$key] += $profits[$order->id][$key];
            }

            $groups[$label]['orders']++;
            $totals['orders']++;
        }

        ksort($groups);

        $rows = [];

        foreach ($groups as $label => $sums) {
            $rows[] = ['label' => (string) $label, ...$sums];
        }

        return ['totals' => $totals, 'rows' => $rows];
    }

    /**
     * @return array<string, int>
     */
    private function blank(): array
    {
        return ['orders' => 0, ...array_fill_keys(self::SUMS, 0)];
    }

    private function label(string $group, Order $order): string
    {
        return match ($group) {
            'zone' => $order->client->zone->name,
            'client' => $order->client->name,
            'kit' => $order->kitVersion->kit->name,
            default => $order->delivery_week->toDateString(),
        };
    }
}
