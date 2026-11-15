<?php

namespace App\Actions\Orders;

use App\Actions\Deliveries\RecordDelivery;
use App\Actions\Stock\CheckDeliveryStock;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidDelivery;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use App\Support\DeliveryReceiver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Records, in one go, the delivery of the kits of the orders of a zone (sub-prefecture) that are ready to be handed
 * over, exactly as the delivery form of the order page does one by one: same rules, same receipt of the delivery
 * (reference, audit line, order moving to delivered when every kit is out). The office picks the orders among those
 * offered and may lower the number of kits of each (a partial delivery) and change who receives; the date and the
 * delivery person are common to the group. The signature and the balance are not taken here: they stay on the order
 * page. An order that fails does not stop the others, and sending the same batch again records nothing twice.
 */
class DeliverKitsForZone
{
    public function __construct(private readonly RecordDelivery $deliveries, private readonly CheckDeliveryStock $stock) {}

    /**
     * The zones that have orders ready to be delivered, with those orders, the kits left and who usually receives.
     *
     * @return list<array{id: int, name: string, orders: list<array{id: int, client: string, reference: string|null, remaining: int, max_kits: int, receiver: string|null}>}>
     */
    public function zones(User $user): array
    {
        $groups = [];

        foreach ($this->ready($user) as $order) {
            $groups[$order->client->zone_id][] = [
                'id' => $order->id,
                'client' => $order->client->name,
                'reference' => $order->reference,
                'remaining' => $order->remainingKits(),
                'max_kits' => $this->stock->maxKits($order),
                'receiver' => DeliveryReceiver::for($order->client_id),
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
     * @param  list<array{order_id: int, kits: int, received_by: string}>  $rows  one per picked order
     * @param  string  $batchKey  sent again with the same rows, it records nothing twice
     * @return array{delivered: int, kits: int, failed: int}
     */
    public function handle(int $zoneId, array $rows, CarbonImmutable $deliveredOn, ?User $agent, string $batchKey, User $user): array
    {
        $result = ['delivered' => 0, 'kits' => 0, 'failed' => 0];
        $orders = $this->ready($user, $zoneId)->keyBy('id');
        $zone = (string) Zone::query()->whereKey($zoneId)->value('name');

        foreach ($rows as $row) {
            $order = $orders->get($row['order_id']);

            if ($order === null || $row['kits'] < 1 || $row['kits'] > $order->remainingKits()) {
                $result['failed']++;

                continue;
            }

            try {
                $this->deliveries->handle(
                    order: $order,
                    kits: $row['kits'],
                    deliveredOn: $deliveredOn,
                    receivedBy: $row['received_by'],
                    agent: $agent,
                    user: $user,
                    idempotencyKey: substr(hash('sha256', $batchKey.'|'.$order->id), 0, 40),
                );
            } catch (InvalidDelivery) {
                $result['failed']++;

                continue;
            }

            AuditLog::record('order.batch_step', $order, [], ['step' => 'delivered', 'zone' => $zone, 'group_of' => count($rows)], $user);

            $result['delivered']++;
            $result['kits'] += $row['kits'];
        }

        return $result;
    }

    /**
     * The orders of the zone whose goods are received and that still have kits to hand over.
     *
     * @return Collection<int, Order>
     */
    private function ready(User $user, ?int $zoneId = null): Collection
    {
        return Order::query()
            ->visibleTo($user)
            ->whereIn('status', OrderStatus::deliverableValues())
            ->when($zoneId !== null, fn ($query) => $query->whereHas('client', fn ($clients) => $clients->where('zone_id', $zoneId)))
            ->with('client:id,name,zone_id')
            ->orderBy('id')
            ->get()
            ->filter(fn (Order $order): bool => $order->remainingKits() > 0)
            ->values();
    }
}
