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
use App\Support\WeekCalendar;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Creates, in one go, the order of a week for the small businesses (TPE) of a locality, as the form of a single
 * order does one by one: same kit rules, same frozen prices, same trace. Only active clients the user may see
 * are concerned, and the server rechecks every one of them. A TPE that already has an order for that week is
 * left alone, so running it again creates nothing; a failure on one TPE does not stop the others. The proformas
 * can be issued at the same time.
 */
class CreateOrdersForLocality
{
    public function __construct(
        private readonly CreateOrder $createOrder,
        private readonly IssueProformasForLocality $proformas,
        private readonly WeekCalendar $calendar,
    ) {}

    /**
     * The active TPE of a locality the user may see, with whether each already has an order for the week.
     *
     * @return Collection<int, array{id: int, name: string, reference: string|null, already: bool}>
     */
    public function candidates(string $locality, ?CarbonInterface $week, User $user): Collection
    {
        $start = $week === null ? null : $this->calendar->startOf($week);

        return $this->eligible($locality, $user)
            ->orderBy('name')
            ->get(['id', 'name', 'reference'])
            ->map(fn (Client $client): array => [
                'id' => $client->id,
                'name' => $client->name,
                'reference' => $client->reference,
                'already' => $start !== null && $this->hasOrderFor($client->id, $start),
            ])
            ->values();
    }

    /**
     * The localities of the active TPE the user may see, with how many.
     *
     * @return list<array{name: string, count: int}>
     */
    public function localities(User $user): array
    {
        return array_values(Client::query()
            ->visibleTo($user)
            ->where('status', ClientStatus::Active->value)
            ->whereNotNull('locality')
            ->where('locality', '!=', '')
            ->groupBy('locality')
            ->orderBy('locality')
            ->get(['locality', DB::raw('count(*) as count')])
            ->map(fn ($row): array => ['name' => (string) $row->getAttribute('locality'), 'count' => (int) $row->getAttribute('count')])
            ->all());
    }

    /**
     * @param  list<int>  $clientIds
     * @return array{created: int, skipped: int, failed: int, proformas: int, proformas_failed: int}
     *
     * @throws OrderPricesMissing when the kit has no price for that week
     */
    public function handle(string $locality, Kit $kit, int $kitsCount, CarbonInterface $week, int $advance, array $clientIds, bool $issueProformas, string $locale, User $user): array
    {
        $start = $this->calendar->startOf($week);
        $version = $kit->versionForWeek($start) ?? throw OrderPricesMissing::forKit(null);

        $clients = $this->eligible($locality, $user)->whereIn('id', $clientIds)->orderBy('name')->get();
        $groupOf = $clients->count();
        $result = ['created' => 0, 'skipped' => 0, 'failed' => 0, 'proformas' => 0, 'proformas_failed' => 0];

        foreach ($clients as $client) {
            try {
                $order = DB::transaction(function () use ($client, $start, $version, $kitsCount, $advance, $user, $locality, $groupOf): ?Order {
                    // The client row is locked so that two requests at once cannot both create the order of the week.
                    Client::query()->whereKey($client->id)->lockForUpdate()->first();

                    if ($this->hasOrderFor($client->id, $start)) {
                        return null;
                    }

                    $order = $this->createOrder->handle($client, $version, $kitsCount, $start, $advance, author: $user);

                    AuditLog::record('order.batch_step', $order, [], ['step' => 'created', 'locality' => $locality, 'group_of' => $groupOf], $user);

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
                    $this->proformas->issueOne($order, $locale, $user, $locality, $groupOf);
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
    private function eligible(string $locality, User $user): Builder
    {
        return Client::query()
            ->visibleTo($user)
            ->where('status', ClientStatus::Active->value)
            ->where('locality', $locality);
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
