<?php

namespace App\Actions\Orders;

use App\Actions\Payments\RecordPayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Exceptions\InvalidPayment;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Zone;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Records, in one go, the advance payment of the orders of a zone (sub-prefecture) that wait for it, exactly as the
 * payment form of the order page does one by one: each payment gets its own receipt number, its audit line, and the
 * order moves to "advance received" as soon as its advance is covered. An order that fails does not stop the others.
 *
 * The office picks the orders among those offered and may change each amount (a client who pays part of the advance);
 * the method and the date are common to the group, the transaction reference is per payment.
 */
class CollectAdvancesForZone
{
    public function __construct(private readonly RecordPayment $payments) {}

    /**
     * The orders of the zone that wait for their advance and have not settled it yet.
     *
     * @return Collection<int, Order>
     */
    public function pending(int $zoneId, User $user): Collection
    {
        return $this->waiting($user, $zoneId);
    }

    /**
     * The zones that have such orders, with those orders and what is still to be collected on each.
     *
     * @return list<array{id: int, name: string, orders: list<array{id: int, client: string, due: int, paid: int, outstanding: int}>}>
     */
    public function zones(User $user): array
    {
        $groups = [];

        foreach ($this->waiting($user) as $order) {
            $paid = $order->netPaid();

            $groups[$order->client->zone_id][] = [
                'id' => $order->id,
                'client' => $order->client->name,
                'due' => $order->advance_due,
                'paid' => $paid,
                'outstanding' => $order->advance_due - $paid,
            ];
        }

        $names = Zone::query()->whereIn('id', array_keys($groups))->pluck('name', 'id');

        $zones = array_map(
            fn (int $id, array $rows): array => ['id' => $id, 'name' => (string) $names->get($id), 'orders' => $rows],
            array_keys($groups),
            $groups,
        );

        usort($zones, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $zones;
    }

    /**
     * @param  list<array{order_id: int, amount: int, reference: string|null}>  $rows  one per picked order
     * @param  string  $batchKey  sent again with the same rows, it records nothing twice
     * @return array{recorded: int, failed: int, total: int}
     */
    public function handle(int $zoneId, PaymentMethod $method, CarbonInterface $paidOn, array $rows, string $batchKey, User $user): array
    {
        $recorded = 0;
        $failed = 0;
        $total = 0;

        $orders = $this->pending($zoneId, $user)->keyBy('id');

        foreach ($rows as $row) {
            $order = $orders->get($row['order_id']);

            if ($order === null) {
                $failed++;

                continue;
            }

            try {
                $this->payments->handle(
                    order: $order,
                    kind: PaymentKind::Advance,
                    amount: $row['amount'],
                    method: $method,
                    reference: $row['reference'],
                    paidOn: $paidOn,
                    user: $user,
                    idempotencyKey: substr(hash('sha256', $batchKey.'|'.$order->id), 0, 40),
                );
                $recorded++;
                $total += $row['amount'];
            } catch (InvalidPayment) {
                $failed++;
            }
        }

        return ['recorded' => $recorded, 'failed' => $failed, 'total' => $total];
    }

    /**
     * @return Collection<int, Order>
     */
    private function waiting(User $user, ?int $zoneId = null): Collection
    {
        return Order::query()
            ->visibleTo($user)
            ->where('status', OrderStatus::ProformaIssued->value)
            ->where('advance_due', '>', 0)
            ->when($zoneId !== null, fn ($query) => $query->whereHas('client', fn ($clients) => $clients->where('zone_id', $zoneId)))
            ->with('client:id,name,zone_id')
            ->orderBy('id')
            ->get()
            ->filter(fn (Order $order): bool => $order->netPaid() < $order->advance_due)
            ->values();
    }
}
