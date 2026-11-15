<?php

namespace App\Actions\Orders;

use App\Enums\ClientStatus;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidDocument;
use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\OrderPricesMissing;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Kit;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use App\Support\BusinessSettings;
use App\Support\ClientReceivables;
use App\Support\WeekCalendar;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Creates, in one go, the order of a week for the small businesses (TPE) of a zone (sub-prefecture), as the form
 * of a single order does one by one: same kit rules, same frozen prices, same trace. Only active clients the user
 * may see are concerned, and the server rechecks every one of them. Two settings decide who is left aside: a TPE
 * that already has an order for that week (so running it again creates nothing), and a TPE whose unpaid kits reach
 * the limit; a failure on one TPE does not stop the others. The proformas can be issued at the same time.
 */
class CreateOrdersForZone
{
    public function __construct(
        private readonly CreateOrder $createOrder,
        private readonly IssueProformasForZone $proformas,
        private readonly WeekCalendar $calendar,
        private readonly BusinessSettings $settings,
        private readonly ClientReceivables $receivables,
    ) {}

    /**
     * The active TPE of a zone the user may see, with the reason each one is left aside, if it is:
     * `already` (an order for the week, when the setting asks for it) or `unpaid` (too many unpaid kits).
     *
     * @return Collection<int, array{id: int, name: string, reference: string|null, reason: 'already'|'unpaid'|null, unpaid_kits: int}>
     */
    public function candidates(int $zoneId, ?CarbonInterface $week, User $user): Collection
    {
        $start = $week === null ? null : $this->calendar->startOf($week);
        $clients = $this->eligible($zoneId, $user)->orderBy('name')->get(['id', 'name', 'reference']);
        $unpaid = $this->receivables->unpaidKits($clients->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all());

        return $clients
            ->map(fn (Client $client): array => [
                'id' => $client->id,
                'name' => $client->name,
                'reference' => $client->reference,
                'reason' => $this->reasonToLeaveAside($client->id, $start, $unpaid[$client->id] ?? 0),
                'unpaid_kits' => $unpaid[$client->id] ?? 0,
            ])
            ->values();
    }

    /**
     * Why a TPE is not given the order of the week, according to the settings; null when it is.
     *
     * @return 'already'|'unpaid'|null
     */
    private function reasonToLeaveAside(int $clientId, ?CarbonInterface $weekStart, int $unpaidKits): ?string
    {
        if ($weekStart !== null && $this->settings->get('batch_skip_clients_with_order') && $this->hasOrderFor($clientId, $weekStart)) {
            return 'already';
        }

        $limit = (int) $this->settings->get('batch_max_unpaid_kits');

        return $limit > 0 && $unpaidKits >= $limit ? 'unpaid' : null;
    }

    /**
     * The zones (sub-prefectures) of the active TPE the user may see, with how many.
     *
     * @return list<array{id: int, name: string, count: int}>
     */
    public function zones(User $user): array
    {
        $counts = Client::query()
            ->visibleTo($user)
            ->where('status', ClientStatus::Active->value)
            ->groupBy('zone_id')
            ->get(['zone_id', DB::raw('count(*) as count')])
            ->mapWithKeys(fn ($row): array => [(int) $row->getAttribute('zone_id') => (int) $row->getAttribute('count')]);

        return array_values(Zone::query()
            ->whereIn('id', $counts->keys())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Zone $zone): array => ['id' => $zone->id, 'name' => $zone->name, 'count' => $counts->get($zone->id, 0)])
            ->all());
    }

    /**
     * @param  list<int>  $clientIds
     * @return array{created: int, skipped: int, failed: int, proformas: int, proformas_failed: int}
     *
     * @throws OrderPricesMissing when the kit has no price for that week
     */
    public function handle(int $zoneId, Kit $kit, int $kitsCount, CarbonInterface $week, int $advance, array $clientIds, bool $issueProformas, string $locale, User $user): array
    {
        $start = $this->calendar->startOf($week);
        $version = $kit->versionForWeek($start) ?? throw OrderPricesMissing::forKit(null);

        $clients = $this->eligible($zoneId, $user)->whereIn('id', $clientIds)->orderBy('name')->get();
        $zone = (string) Zone::query()->whereKey($zoneId)->value('name');
        $groupOf = $clients->count();
        $result = ['created' => 0, 'skipped' => 0, 'failed' => 0, 'proformas' => 0, 'proformas_failed' => 0];

        foreach ($clients as $client) {
            try {
                $order = DB::transaction(function () use ($client, $start, $version, $kitsCount, $advance, $user, $zone, $groupOf): ?Order {
                    // The client row is locked so that two requests at once cannot both create the order of the week.
                    Client::query()->whereKey($client->id)->lockForUpdate()->first();

                    if ($this->reasonToLeaveAside($client->id, $start, $this->receivables->unpaidKits([$client->id])[$client->id] ?? 0) !== null) {
                        return null;
                    }

                    $order = $this->createOrder->handle($client, $version, $kitsCount, $start, $advance, author: $user);

                    AuditLog::record('order.batch_step', $order, [], ['step' => 'created', 'zone' => $zone, 'group_of' => $groupOf], $user);

                    return $order;
                });
            } catch (OrderPricesMissing) {
                $result['failed']++;

                continue;
            }

            if ($order === null) {
                $result['skipped']++;

                continue;
            }

            $result['created']++;

            if ($issueProformas) {
                try {
                    $this->proformas->issueOne($order, $locale, $user, $zone, $groupOf);
                    $result['proformas']++;
                } catch (InvalidDocument|InvalidOrderTransition) {
                    // The order exists as a draft: its proforma can be issued later from the orders list.
                    $result['proformas_failed']++;
                }
            }
        }

        return $result;
    }

    /**
     * @return Builder<Client>
     */
    private function eligible(int $zoneId, User $user): Builder
    {
        return Client::query()
            ->visibleTo($user)
            ->where('status', ClientStatus::Active->value)
            ->where('zone_id', $zoneId);
    }

    private function hasOrderFor(int $clientId, CarbonInterface $weekStart): bool
    {
        return Order::query()
            ->where('client_id', $clientId)
            ->whereDate('delivery_week', $weekStart->toDateString())
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->exists();
    }
}
