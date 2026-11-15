<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What each small business (TPE) still owes, order by order and in total. An order counts as soon as it exists and
 * is not cancelled, whatever its step: an unpaid advance or an unpaid balance are both "still to pay", and nothing
 * here stops a TPE with a debt from ordering again. The remaining amount of an order is its price (net of
 * discounts) minus what was received (refunds taken off).
 *
 * @phpstan-type OrderRow array{id: int, reference: string|null, delivery_week: string, status: string, total: int, advance_due: int, paid: int, remaining: int, advance_outstanding: int}
 * @phpstan-type ClientRow array{id: int, name: string, phone: string, zone: string, orders_count: int, total: int, paid: int, remaining: int, orders: list<OrderRow>}
 */
class ClientReceivables
{
    private const NET_PAID = "(select coalesce(sum(case when kind = 'refund' then -amount else amount end), 0) from payments where payments.order_id = orders.id)";

    /**
     * The clients that owe something, the biggest debt first, each with the unpaid orders behind it.
     *
     * @return Collection<int, ClientRow>
     */
    public function clients(User $user, ?int $zoneId = null): Collection
    {
        $orders = Order::query()
            ->visibleTo($user)
            ->select('orders.*')
            ->selectRaw(self::NET_PAID.' as paid_amount')
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->whereRaw('total_sale > '.self::NET_PAID)
            ->when($zoneId !== null, fn (Builder $query) => $query->whereHas('client', fn ($clients) => $clients->where('zone_id', $zoneId)))
            ->with('client.zone')
            ->orderBy('delivery_week')
            ->orderBy('id')
            ->get();

        return $orders
            ->groupBy('client_id')
            ->map(function (Collection $group): array {
                /** @var Order $first */
                $first = $group->first();
                $rows = $group->map(fn (Order $order): array => $this->row($order))->values();

                return [
                    'id' => $first->client->id,
                    'name' => $first->client->name,
                    'phone' => $first->client->phone,
                    'zone' => $first->client->zone->name,
                    'orders_count' => $rows->count(),
                    'total' => (int) $rows->sum('total'),
                    'paid' => (int) $rows->sum('paid'),
                    'remaining' => (int) $rows->sum('remaining'),
                    'orders' => array_values($rows->all()),
                ];
            })
            ->sortByDesc('remaining')
            ->values();
    }

    /**
     * What one client still owes over all its orders (the figure of its page).
     */
    public function owedBy(int $clientId): int
    {
        return (int) Order::query()
            ->where('client_id', $clientId)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->selectRaw('coalesce(sum(case when total_sale > '.self::NET_PAID.' then total_sale - '.self::NET_PAID.' else 0 end), 0) as owed')
            ->value('owed');
    }

    /**
     * @return OrderRow
     */
    private function row(Order $order): array
    {
        $paid = (int) $order->getAttribute('paid_amount');

        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'delivery_week' => $order->delivery_week->toDateString(),
            'status' => $order->status->value,
            'total' => $order->total_sale,
            'advance_due' => $order->advance_due,
            'paid' => $paid,
            'remaining' => max(0, $order->total_sale - $paid),
            'advance_outstanding' => max(0, $order->advance_due - $paid),
        ];
    }
}
