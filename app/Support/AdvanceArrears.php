<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\Order;
use App\Models\Zone;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The orders whose advance should have been paid by now: still at the draft or proforma stage, delivery week
 * reached, advance not fully received. One source for the page and for its export, so they never disagree.
 *
 * @phpstan-type Row array{id: int, reference: string|null, client: string, phone: string, zone: string, delivery_week: string, advance_due: int, paid_amount: int, advance_outstanding: int}
 */
class AdvanceArrears
{
    private const NET_PAID = "(select coalesce(sum(case when kind = 'refund' then -amount else amount end), 0) from payments where payments.order_id = orders.id)";

    /**
     * The columns the list can be sorted by (the keys of the column headers).
     */
    public const SORTABLE = ['reference', 'client', 'zone', 'week', 'due', 'outstanding'];

    /**
     * Every late order (the export), in the order the screen is sorted.
     *
     * @param  'asc'|'desc'  $direction
     * @return Collection<int, Row>
     */
    public function rows(?int $zoneId = null, ?string $sort = null, string $direction = 'asc'): Collection
    {
        return $this->query($zoneId, $sort, $direction)->get()->map(fn (Order $order): array => $this->row($order))->values();
    }

    /**
     * One page of late orders (the screen).
     *
     * @param  'asc'|'desc'  $direction
     * @return LengthAwarePaginator<int, Row>
     */
    public function page(?int $zoneId, int $perPage, ?string $sort = null, string $direction = 'asc'): LengthAwarePaginator
    {
        return $this->query($zoneId, $sort, $direction)->paginate($perPage)->withQueryString()->through(fn (Order $order): array => $this->row($order));
    }

    /**
     * What is still to be received on all the late orders, not only the page shown.
     */
    public function outstanding(?int $zoneId = null): int
    {
        return (int) $this->query($zoneId)->sum(DB::raw('advance_due - '.self::NET_PAID));
    }

    /**
     * @param  'asc'|'desc'  $direction
     * @return Builder<Order>
     */
    private function query(?int $zoneId, ?string $sort = null, string $direction = 'asc'): Builder
    {
        $query = Order::query()
            ->select('orders.*')
            ->selectRaw(self::NET_PAID.' as paid_amount')
            ->whereIn('status', [OrderStatus::Draft->value, OrderStatus::ProformaIssued->value])
            ->whereDate('delivery_week', '<=', CarbonImmutable::today())
            ->whereRaw('advance_due > '.self::NET_PAID)
            ->when($zoneId !== null, fn (Builder $query) => $query->whereHas('client', fn (BuilderContract $clients) => $clients->where('zone_id', $zoneId)))
            ->with('client.zone');

        return $this->sorted($query, $sort, $direction)->orderBy('orders.id', $sort === null ? 'asc' : $direction);
    }

    /**
     * The sort asked for, or the oldest delivery week first. Only the listed columns are accepted.
     *
     * @param  Builder<Order>  $query
     * @param  'asc'|'desc'  $direction
     * @return Builder<Order>
     */
    private function sorted(Builder $query, ?string $sort, string $direction): Builder
    {
        return match ($sort) {
            'reference' => $query->orderBy('reference', $direction),
            'client' => $query->orderBy(Client::query()->select('name')->whereColumn('clients.id', 'orders.client_id'), $direction),
            'zone' => $query->orderBy(Zone::query()->select('zones.name')
                ->join('clients', 'clients.zone_id', '=', 'zones.id')
                ->whereColumn('clients.id', 'orders.client_id'), $direction),
            'week' => $query->orderBy('delivery_week', $direction),
            'due' => $query->orderBy('advance_due', $direction),
            'outstanding' => $direction === 'desc'
                ? $query->orderByRaw('advance_due - '.self::NET_PAID.' desc')
                : $query->orderByRaw('advance_due - '.self::NET_PAID.' asc'),
            default => $query->orderBy('delivery_week'),
        };
    }

    /**
     * @return Row
     */
    private function row(Order $order): array
    {
        $paid = (int) $order->getAttribute('paid_amount');

        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'client' => $order->client->name,
            'phone' => $order->client->phone,
            'zone' => $order->client->zone->name,
            'delivery_week' => $order->delivery_week->toDateString(),
            'advance_due' => $order->advance_due,
            'paid_amount' => $paid,
            'advance_outstanding' => $order->advance_due - $paid,
        ];
    }
}
