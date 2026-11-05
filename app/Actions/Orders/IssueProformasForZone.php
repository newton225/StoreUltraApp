<?php

namespace App\Actions\Orders;

use App\Documents\DocumentIssuer;
use App\Enums\DocumentType;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidDocument;
use App\Exceptions\InvalidOrderTransition;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Issues, in one go, the proforma of every order still waiting for it (status "draft") whose client is in a
 * given zone (sub-prefecture), and moves each of these orders to "proforma issued", exactly as the button of the
 * order page does one by one. An order that fails does not stop the others; it is counted as failed.
 */
class IssueProformasForZone
{
    public function __construct(private readonly DocumentIssuer $issuer, private readonly TransitionOrder $transition) {}

    /**
     * The orders a batch would handle: draft orders of the zone that the user may see.
     *
     * @return Builder<Order>
     */
    public function pending(int $zoneId, User $user): Builder
    {
        return Order::query()
            ->visibleTo($user)
            ->where('status', OrderStatus::Draft->value)
            ->whereHas('client', fn (Builder $clients) => $clients->where('zone_id', $zoneId));
    }

    /**
     * The zones that have draft orders, with how many.
     *
     * @return list<array{id: int, name: string, count: int}>
     */
    public function zones(User $user): array
    {
        $rows = Order::query()
            ->visibleTo($user)
            ->where('orders.status', OrderStatus::Draft->value)
            ->join('clients', 'clients.id', '=', 'orders.client_id')
            ->join('zones', 'zones.id', '=', 'clients.zone_id')
            ->groupBy('zones.id', 'zones.name')
            ->orderBy('zones.name')
            ->get(['zones.id as zone_id', 'zones.name as zone_name', DB::raw('count(*) as count')]);

        return array_values($rows
            ->map(fn ($row): array => [
                'id' => (int) $row->getAttribute('zone_id'),
                'name' => (string) $row->getAttribute('zone_name'),
                'count' => (int) $row->getAttribute('count'),
            ])
            ->all());
    }

    /**
     * @return array{issued: int, failed: int}
     */
    public function handle(int $zoneId, string $locale, User $user): array
    {
        $issued = 0;
        $failed = 0;

        $orders = $this->pending($zoneId, $user)->orderBy('id')->get();
        $zone = (string) Zone::query()->whereKey($zoneId)->value('name');
        $total = $orders->count();

        foreach ($orders as $order) {
            try {
                $this->issueOne($order, $locale, $user, $zone, $total);

                $issued++;
            } catch (InvalidDocument|InvalidOrderTransition) {
                $failed++;
            }
        }

        return ['issued' => $issued, 'failed' => $failed];
    }

    /**
     * Issue the proforma of one order (never a second one) and move it to "proforma issued", with the trace of the
     * group it belongs to, all in one transaction.
     *
     * @throws InvalidDocument|InvalidOrderTransition
     */
    public function issueOne(Order $order, string $locale, User $user, string $zone, int $groupOf): void
    {
        DB::transaction(function () use ($order, $locale, $user, $zone, $groupOf): void {
            $hasProforma = Document::query()
                ->where('type', DocumentType::Proforma->value)
                ->where('subject_type', $order->getMorphClass())
                ->where('subject_id', $order->id)
                ->whereNull('cancelled_at')
                ->exists();

            if (! $hasProforma) {
                $this->issuer->issue(DocumentType::Proforma, $order, $locale, $user);
            }

            $this->transition->handle($order, OrderStatus::ProformaIssued, null, $user);

            AuditLog::record('order.batch_step', $order, [], ['step' => 'proforma_issued', 'zone' => $zone, 'group_of' => $groupOf], $user);
        });
    }
}
