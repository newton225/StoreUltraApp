<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Records, in one go, the step "advance received" for the orders of a locality whose advance is fully paid
 * (net of refunds). The office picks the orders among those offered; the payments themselves were recorded
 * one by one, so this only moves the orders on, exactly as the button of the order page does. An order that
 * fails does not stop the others.
 */
class ReceiveAdvancesForLocality
{
    public function __construct(private readonly TransitionOrder $transition) {}

    /**
     * The orders of the locality that wait for their advance and have settled it.
     *
     * @return Collection<int, Order>
     */
    public function settled(string $locality, User $user): Collection
    {
        return Order::query()
            ->visibleTo($user)
            ->where('status', OrderStatus::ProformaIssued->value)
            ->where('advance_due', '>', 0)
            ->whereHas('client', fn ($clients) => $clients->where('locality', $locality))
            ->with('client:id,name,locality')
            ->orderBy('id')
            ->get()
            ->filter(fn (Order $order): bool => $order->netPaid() >= $order->advance_due)
            ->values();
    }

    /**
     * The localities that have orders with a settled advance, with those orders.
     *
     * @return list<array{name: string, orders: list<array{id: int, client: string, due: int, paid: int}>}>
     */
    public function localities(User $user): array
    {
        $orders = Order::query()
            ->visibleTo($user)
            ->where('status', OrderStatus::ProformaIssued->value)
            ->where('advance_due', '>', 0)
            ->with('client:id,name,locality')
            ->orderBy('id')
            ->get();

        $groups = [];

        foreach ($orders as $order) {
            $locality = (string) $order->client->locality;
            $paid = $order->netPaid();

            if ($locality === '' || $paid < $order->advance_due) {
                continue;
            }

            $groups[$locality][] = ['id' => $order->id, 'client' => $order->client->name, 'due' => $order->advance_due, 'paid' => $paid];
        }

        ksort($groups);

        return array_map(fn (string $name, array $rows): array => ['name' => $name, 'orders' => $rows], array_keys($groups), $groups);
    }

    /**
     * @param  array<int, int>  $orderIds  the orders picked by the office
     * @return array{recorded: int, failed: int}
     */
    public function handle(string $locality, array $orderIds, User $user): array
    {
        $recorded = 0;
        $failed = 0;

        $picked = $this->settled($locality, $user)->whereIn('id', $orderIds);

        foreach ($picked as $order) {
            try {
                $this->transition->handle($order, OrderStatus::AdvanceReceived, null, $user);
                AuditLog::record('order.batch_step', $order, [], ['step' => 'advance_received', 'locality' => $locality, 'paid' => $order->netPaid(), 'due' => $order->advance_due, 'group_of' => $picked->count()], $user);
                $recorded++;
            } catch (InvalidOrderTransition) {
                $failed++;
            }
        }

        return ['recorded' => $recorded, 'failed' => $failed];
    }
}
