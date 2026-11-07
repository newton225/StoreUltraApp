<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Gate;

/**
 * The figures of the dashboard: indicators over a period, the synthesis by shop, the profit and the
 * alerts. Each user only gets what the profile may see: field agents have their sub-prefectures'
 * orders and clients only, and no figure about shops or profit.
 */
class DashboardReport
{
    public function __construct(
        private OrderProfit $profit,
        private ShopAccounts $shopAccounts,
        private AttachmentAlerts $attachmentAlerts,
        private BusinessSettings $settings,
    ) {}

    /**
     * @param  array{from: string, to: string, zone: int|null, kit: int|null, shop: int|null}  $filters
     * @return array{
     *     indicators: array<string, mixed>,
     *     shops: array<string, mixed>|null,
     *     profit: array<string, int>|null,
     *     weekly: list<array<string, int|string|null>>,
     *     alerts: array<string, mixed>
     * }
     */
    public function build(User $user, array $filters): array
    {
        $orders = $this->scope($user, $filters)->get();
        $live = $orders->where('status', '!=', OrderStatus::Cancelled);
        $canManage = Gate::forUser($user)->allows('manage-orders');
        $canViewProfit = Gate::forUser($user)->allows('view-profit');

        return [
            'indicators' => $this->indicators($user, $filters, $orders, $live),
            'shops' => $canManage ? $this->shops($live, $filters['shop'], $canViewProfit) : null,
            'profit' => $canViewProfit ? $this->profitOf($live) : null,
            'weekly' => $this->weekly($live, $canViewProfit),
            'alerts' => $this->alerts($user, $filters),
        ];
    }

    /**
     * The figures of the period week by week, for the charts: what was sold, what was collected
     * (advances and balances, less refunds), the kits ordered and delivered, and the margins when
     * the user may see the profit (null otherwise). A week is the Monday of the delivery week.
     *
     * @param  EloquentCollection<int, Order>  $live
     * @return list<array<string, int|string|null>>
     */
    private function weekly(EloquentCollection $live, bool $withProfit): array
    {
        $ids = $live->pluck('id')->all();
        $paid = $this->paidByOrder($ids);
        $delivered = Delivery::query()
            ->selectRaw('order_id, sum(kits_delivered) as total')
            ->whereIn('order_id', $ids)
            ->groupBy('order_id')
            ->pluck('total', 'order_id');
        $profits = $withProfit ? $this->profit->forOrders($live) : [];

        $weeks = [];
        $theoretical = [];
        $real = [];

        foreach ($live as $order) {
            $week = $order->delivery_week->toDateString();

            $weeks[$week] ??= [
                'week' => $week,
                'sale' => 0,
                'collected' => 0,
                'kits_ordered' => 0,
                'kits_delivered' => 0,
            ];
            $theoretical[$week] ??= 0;
            $real[$week] ??= 0;

            $weeks[$week]['sale'] += $order->total_sale;
            $weeks[$week]['collected'] += $paid[$order->id] ?? 0;
            $weeks[$week]['kits_ordered'] += $order->kits_count;
            $weeks[$week]['kits_delivered'] += (int) ($delivered[$order->id] ?? 0);

            $theoretical[$week] += $profits[$order->id]['theoretical_margin'] ?? 0;
            $real[$week] += $profits[$order->id]['real_margin'] ?? 0;
        }

        ksort($weeks);

        return array_values(array_map(fn (array $row): array => [
            ...$row,
            'theoretical_margin' => $withProfit ? $theoretical[$row['week']] : null,
            'real_margin' => $withProfit ? $real[$row['week']] : null,
        ], $weeks));
    }

    /**
     * The orders of the period for the user, whatever their state.
     *
     * @param  array{from: string, to: string, zone: int|null, kit: int|null, shop: int|null}  $filters
     * @return Builder<Order>
     */
    public function scope(User $user, array $filters): Builder
    {
        return Order::query()
            ->visibleTo($user)
            ->whereDate('delivery_week', '>=', $filters['from'])
            ->whereDate('delivery_week', '<=', $filters['to'])
            ->when($filters['zone'] !== null, fn (Builder $query) => $query->whereHas('client', fn (Builder $clients) => $clients->where('zone_id', $filters['zone'])))
            ->when($filters['kit'] !== null, fn (Builder $query) => $query->whereHas('kitVersion', fn (Builder $versions) => $versions->where('kit_id', $filters['kit'])))
            ->orderBy('delivery_week')
            ->orderBy('id');
    }

    /**
     * @param  array{from: string, to: string, zone: int|null, kit: int|null, shop: int|null}  $filters
     * @param  EloquentCollection<int, Order>  $orders
     * @param  EloquentCollection<int, Order>  $live
     * @return array<string, mixed>
     */
    private function indicators(User $user, array $filters, EloquentCollection $orders, EloquentCollection $live): array
    {
        $ids = $live->pluck('id')->all();

        $byStatus = [];

        foreach (OrderStatus::cases() as $status) {
            $byStatus[$status->value] = $orders->where('status', $status)->count();
        }

        $delivered = Delivery::query()
            ->selectRaw('order_id, sum(kits_delivered) as total')
            ->whereIn('order_id', $ids)
            ->groupBy('order_id')
            ->pluck('total', 'order_id');

        $kitsOrdered = 0;
        $kitsDelivered = 0;
        $kitsRemaining = 0;

        foreach ($live as $order) {
            $done = (int) ($delivered[$order->id] ?? 0);
            $kitsOrdered += $order->kits_count;
            $kitsDelivered += $done;
            $kitsRemaining += max(0, $order->kits_count - $done);
        }

        $paid = $this->paidByOrder($ids);
        $received = ['advance' => 0, 'balance' => 0, 'refund' => 0];

        foreach (Payment::query()->selectRaw('kind, sum(amount) as total')->whereIn('order_id', $ids)->groupBy('kind')->pluck('total', 'kind') as $kind => $total) {
            $received[(string) $kind] = (int) $total;
        }

        $receivables = 0;

        foreach ($live->where('status', OrderStatus::Delivered) as $order) {
            $receivables += max(0, $order->total_sale - ($paid[$order->id] ?? 0));
        }

        $clients = Client::query()
            ->visibleTo($user)
            ->countingTowardsCap()
            ->when($filters['zone'] !== null, fn (Builder $query) => $query->where('zone_id', $filters['zone']))
            ->count();

        return [
            'active_clients' => $clients,
            'orders' => $live->count(),
            'orders_by_status' => $byStatus,
            'kits_ordered' => $kitsOrdered,
            'kits_delivered' => $kitsDelivered,
            'kits_remaining' => $kitsRemaining,
            'sale_total' => (int) $live->sum('total_sale'),
            'advances' => $received['advance'],
            'balances' => $received['balance'],
            'refunds' => $received['refund'],
            'receivables' => $receivables,
        ];
    }

    /**
     * The synthesis by shop for the orders of the period, and what is owed to the shops overall.
     *
     * @param  EloquentCollection<int, Order>  $live
     * @return array<string, mixed>
     */
    private function shops(EloquentCollection $live, ?int $shopId, bool $withProfit): array
    {
        $ids = $live->pluck('id')->all();
        $paid = $this->paidByOrder($ids);

        $items = OrderItem::query()
            ->whereIn('order_id', $ids)
            ->with('purchaseOrder.items')
            ->get(['order_id', 'shop_id', 'product_id', 'purchase_order_id', 'quantity', 'unit_purchase_price', 'line_sale_total'])
            ->groupBy('order_id');

        $rows = [];

        foreach ($items as $orderId => $lines) {
            $weights = [];

            foreach ($lines as $line) {
                $key = $line->shop_id ?? 0;
                $weights[$key] = ($weights[$key] ?? 0) + $line->quantity * $line->unit_purchase_price;
                $rows[$key]['sale'] = ($rows[$key]['sale'] ?? 0) + (int) $line->line_sale_total;
                $rows[$key]['actual'] = ($rows[$key]['actual'] ?? 0) + $this->profit->actualCost($line);
            }

            $shares = AdvanceAllocation::split(max(0, $paid[$orderId] ?? 0), $weights);

            foreach ($weights as $key => $purchase) {
                $rows[$key]['purchase'] = ($rows[$key]['purchase'] ?? 0) + $purchase;
                $rows[$key]['advance'] = ($rows[$key]['advance'] ?? 0) + $shares[$key];
            }
        }

        $names = Shop::query()->pluck('name', 'id');
        $purchaseTotal = 0;

        foreach ($rows as $row) {
            $purchaseTotal += $row['purchase'];
        }

        $summary = [];

        foreach ($rows as $key => $row) {
            if ($shopId !== null && $key !== $shopId) {
                continue;
            }

            $summary[] = [
                'shop' => $key === 0 ? '—' : (string) ($names[$key] ?? '—'),
                'purchase' => $row['purchase'],
                'sale' => $row['sale'] ?? 0,
                'advance' => $row['advance'],
                'percent' => $purchaseTotal > 0 ? round($row['purchase'] / $purchaseTotal * 100, 1) : 0.0,
                'balance' => $row['purchase'] - $row['advance'],
                // The profit of a shop's lines: sale minus what was planned, then minus what was really paid (before logistics).
                ...($withProfit ? [
                    'theoretical_margin' => ($row['sale'] ?? 0) - $row['purchase'],
                    'real_margin' => ($row['sale'] ?? 0) - ($row['actual'] ?? 0),
                ] : []),
            ];
        }

        usort($summary, fn (array $a, array $b): int => strcmp($a['shop'], $b['shop']));

        $accounts = $this->shopAccounts->all();
        $debt = 0;

        foreach ($accounts as $account) {
            if ($shopId !== null && $account['shop_id'] !== $shopId) {
                continue;
            }

            $debt += max(0, $account['debt']);
        }

        return ['summary' => $summary, 'debts' => $debt, 'with_profit' => $withProfit];
    }

    /**
     * @param  EloquentCollection<int, Order>  $live
     * @return array<string, int>
     */
    private function profitOf(EloquentCollection $live): array
    {
        $totals = ['theoretical_margin' => 0, 'real_margin' => 0, 'projected_margin' => 0, 'gap' => 0, 'logistics' => 0, 'actual_purchase' => 0];

        foreach ($this->profit->forOrders($live) as $figures) {
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $figures[$key];
            }
        }

        return $totals;
    }

    /**
     * What needs attention now for a user, whatever the screen: the figures of the notification bell.
     *
     * @return array<string, mixed>
     */
    public function alertsFor(User $user): array
    {
        $today = CarbonImmutable::today()->toDateString();

        return $this->alerts($user, ['from' => $today, 'to' => $today, 'zone' => null, 'kit' => null, 'shop' => null]);
    }

    /**
     * What needs attention now. It does not depend on the period, only on what the user may see.
     *
     * @param  array{from: string, to: string, zone: int|null, kit: int|null, shop: int|null}  $filters
     * @return array<string, mixed>
     */
    private function alerts(User $user, array $filters): array
    {
        $today = CarbonImmutable::today();
        $visible = fn (): Builder => Order::query()
            ->visibleTo($user)
            ->when($filters['zone'] !== null, fn (Builder $query) => $query->whereHas('client', fn (Builder $clients) => $clients->where('zone_id', $filters['zone'])));

        $late = $this->lateAdvances($user, $filters['zone']);
        $lateCount = count($late);
        $lateAmount = 0;

        foreach ($late as $row) {
            $lateAmount += $row['outstanding'];
        }

        $blockedAfterDays = (int) $this->settings->get('blocked_order_days');

        $blocked = $visible()
            ->whereIn('status', [
                OrderStatus::Draft->value,
                OrderStatus::ProformaIssued->value,
                OrderStatus::AdvanceReceived->value,
                OrderStatus::Ordered->value,
                OrderStatus::Received->value,
            ])
            ->whereDate('delivery_week', '<', $today->subDays($blockedAfterDays))
            ->count();

        $alerts = [
            'late_advances' => ['count' => $lateCount, 'amount' => $lateAmount],
            'blocked_orders' => $blocked,
            'blocked_after_days' => $blockedAfterDays,
            'client_cap' => null,
            'attachments' => null,
        ];

        if (Gate::forUser($user)->allows('manage-orders')) {
            $cap = (int) $this->settings->get('client_cap');

            if ($cap > 0) {
                $used = Client::query()->countingTowardsCap()->count();
                $alerts['client_cap'] = ['used' => $used, 'cap' => $cap, 'reached' => $used >= $cap];
            }
        }

        if (Gate::forUser($user)->allows('manage-settings')) {
            $documents = $this->attachmentAlerts->all();
            $alerts['attachments'] = [
                'missing' => count($documents['missing']),
                'expiring' => count($documents['expiring']),
                'expired' => count($documents['expired']),
            ];
        }

        return $alerts;
    }

    /**
     * The orders whose advance should have been paid by now: the delivery week has come and the
     * client has not paid all of it.
     *
     * @return list<array{order: Order, paid: int, outstanding: int}>
     */
    public function lateAdvances(User $user, ?int $zone): array
    {
        $orders = Order::query()
            ->visibleTo($user)
            ->with('client.zone')
            ->whereIn('status', [OrderStatus::Draft->value, OrderStatus::ProformaIssued->value])
            ->whereDate('delivery_week', '<=', CarbonImmutable::today())
            ->when($zone !== null, fn (Builder $query) => $query->whereHas('client', fn (Builder $clients) => $clients->where('zone_id', $zone)))
            ->orderBy('delivery_week')
            ->orderBy('id')
            ->get();
        $paid = $this->paidByOrder($orders->pluck('id')->all());

        $late = [];

        foreach ($orders as $order) {
            $outstanding = $order->advance_due - ($paid[$order->id] ?? 0);

            if ($outstanding > 0) {
                $late[] = ['order' => $order, 'paid' => $paid[$order->id] ?? 0, 'outstanding' => $outstanding];
            }
        }

        return $late;
    }

    /**
     * What each order has really received: payments minus refunds.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function paidByOrder(array $ids): array
    {
        $paid = [];

        $rows = Payment::query()
            ->selectRaw("order_id, coalesce(sum(case when kind = 'refund' then -amount else amount end), 0) as total")
            ->whereIn('order_id', $ids)
            ->groupBy('order_id')
            ->pluck('total', 'order_id');

        foreach ($rows as $orderId => $total) {
            $paid[(int) $orderId] = (int) $total;
        }

        return $paid;
    }
}
