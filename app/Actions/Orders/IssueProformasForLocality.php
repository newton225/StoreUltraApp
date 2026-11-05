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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Issues, in one go, the proforma of every order still waiting for it (status "draft") whose client is in a
 * given locality, and moves each of these orders to "proforma issued", exactly as the button of the order
 * page does one by one. An order that fails does not stop the others; it is counted as failed.
 */
class IssueProformasForLocality
{
    public function __construct(private readonly DocumentIssuer $issuer, private readonly TransitionOrder $transition) {}

    /**
     * The orders a batch would handle: draft orders of the locality that the user may see.
     *
     * @return Builder<Order>
     */
    public function pending(string $locality, User $user): Builder
    {
        return Order::query()
            ->visibleTo($user)
            ->where('status', OrderStatus::Draft->value)
            ->whereHas('client', fn (Builder $clients) => $clients->where('locality', $locality));
    }

    /**
     * The localities that have draft orders, with how many.
     *
     * @return list<array{name: string, count: int}>
     */
    public function localities(User $user): array
    {
        $rows = Order::query()
            ->visibleTo($user)
            ->where('orders.status', OrderStatus::Draft->value)
            ->join('clients', 'clients.id', '=', 'orders.client_id')
            ->whereNotNull('clients.locality')
            ->where('clients.locality', '!=', '')
            ->groupBy('clients.locality')
            ->orderBy('clients.locality')
            ->get(['clients.locality as name', DB::raw('count(*) as count')]);

        return array_values($rows
            ->map(fn ($row): array => ['name' => (string) $row->getAttribute('name'), 'count' => (int) $row->getAttribute('count')])
            ->all());
    }

    /**
     * @return array{issued: int, failed: int}
     */
    public function handle(string $locality, string $locale, User $user): array
    {
        $issued = 0;
        $failed = 0;

        $orders = $this->pending($locality, $user)->orderBy('id')->get();
        $total = $orders->count();

        foreach ($orders as $order) {
            try {
                $this->issueOne($order, $locale, $user, $locality, $total);

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
    public function issueOne(Order $order, string $locale, User $user, string $locality, int $groupOf): void
    {
        DB::transaction(function () use ($order, $locale, $user, $locality, $groupOf): void {
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

            AuditLog::record('order.batch_step', $order, [], ['step' => 'proforma_issued', 'locality' => $locality, 'group_of' => $groupOf], $user);
        });
    }
}
