<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The content of the CSV exports: a header line and the rows, with the same filters as the dashboard.
 * Amounts stay plain integers in francs so a spreadsheet can add them up.
 */
class CsvReports
{
    public function __construct(private DashboardReport $dashboard, private ProfitReport $profits) {}

    /**
     * @param  array{from: string, to: string, zone: int|null, kit: int|null, shop: int|null}  $filters
     * @return array{headers: list<string>, rows: list<list<int|float|string|null>>}
     */
    public function build(string $report, User $user, array $filters): array
    {
        $table = match ($report) {
            'orders' => $this->orders($user, $filters),
            'clients' => $this->clients($user, $filters),
            'payments' => $this->payments($user, $filters),
            'arrears' => $this->arrears($user, $filters),
            'expenses' => $this->expenses($filters),
            'shops' => $this->shops($user, $filters),
            default => $this->profitRows($filters),
        };

        return [
            'headers' => array_map(fn (string $column): string => __('dashboard.csv.'.$column), $table['columns']),
            'rows' => $table['rows'],
        ];
    }

    /**
     * @param  array{from: string, to: string, zone: int|null, kit: int|null, shop: int|null}  $filters
     * @return array{columns: list<string>, rows: list<list<int|float|string|null>>}
     */
    private function orders(User $user, array $filters): array
    {
        $orders = $this->dashboard->scope($user, $filters)->with(['client.zone', 'kitVersion.kit'])->get();
        $ids = $orders->pluck('id')->all();

        $paid = [];

        foreach (Payment::query()
            ->selectRaw("order_id, coalesce(sum(case when kind = 'refund' then -amount else amount end), 0) as total")
            ->whereIn('order_id', $ids)->groupBy('order_id')->pluck('total', 'order_id') as $orderId => $total) {
            $paid[(int) $orderId] = (int) $total;
        }

        $rows = [];

        foreach ($orders as $order) {
            $rows[] = [
                $order->reference,
                $order->delivery_week->toDateString(),
                $order->client->name,
                $order->client->zone->name,
                $order->kitVersion->kit->name,
                $order->kits_count,
                __('orders.statuses.'.$order->status->value),
                $order->total_sale,
                $order->advance_due,
                $paid[$order->id] ?? 0,
            ];
        }

        return [
            'columns' => ['reference', 'week', 'client', 'zone', 'kit', 'kits', 'status', 'sale', 'advance_due', 'paid'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{from: string, to: string, zone: int|null, kit: int|null, shop: int|null}  $filters
     * @return array{columns: list<string>, rows: list<list<int|float|string|null>>}
     */
    private function clients(User $user, array $filters): array
    {
        $rows = [];

        $clients = Client::query()
            ->visibleTo($user)
            ->with('zone')
            ->when($filters['zone'] !== null, fn (Builder $query) => $query->where('zone_id', $filters['zone']))
            ->orderBy('name')
            ->get();

        foreach ($clients as $client) {
            $rows[] = [
                $client->reference,
                $client->name,
                $client->phone,
                $client->commercial_number,
                $client->zone->name,
                $client->locality,
                $client->status->value,
            ];
        }

        return [
            'columns' => ['reference', 'client', 'phone', 'commercial_number', 'zone', 'locality', 'client_status'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{from: string, to: string, zone: int|null, kit: int|null, shop: int|null}  $filters
     * @return array{columns: list<string>, rows: list<list<int|float|string|null>>}
     */
    private function payments(User $user, array $filters): array
    {
        $rows = [];

        $payments = Payment::query()
            ->with(['order.client', 'method'])
            ->whereDate('paid_on', '>=', $filters['from'])
            ->whereDate('paid_on', '<=', $filters['to'])
            ->whereHas('order', fn (Builder $orders) => $orders->visibleTo($user)
                ->when($filters['zone'] !== null, fn (Builder $query) => $query->whereHas('client', fn (Builder $clients) => $clients->where('zone_id', $filters['zone']))))
            ->orderBy('paid_on')
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            $rows[] = [
                $payment->receipt_number,
                $payment->paid_on->toDateString(),
                __('payments.kinds.'.$payment->kind->value),
                $payment->kind->value === 'refund' ? -$payment->amount : $payment->amount,
                $payment->method->name,
                $payment->reference,
                $payment->order->reference,
                $payment->order->client->name,
            ];
        }

        return [
            'columns' => ['receipt', 'paid_on', 'kind', 'amount', 'method', 'payment_reference', 'order', 'client'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{from: string, to: string, zone: int|null, kit: int|null, shop: int|null}  $filters
     * @return array{columns: list<string>, rows: list<list<int|float|string|null>>}
     */
    private function arrears(User $user, array $filters): array
    {
        $rows = [];

        foreach ($this->dashboard->lateAdvances($user, $filters['zone']) as $late) {
            $order = $late['order'];

            $rows[] = [
                $order->reference,
                $order->delivery_week->toDateString(),
                $order->client->name,
                $order->client->phone,
                $order->client->zone->name,
                $order->advance_due,
                $late['paid'],
                $late['outstanding'],
            ];
        }

        return [
            'columns' => ['order', 'week', 'client', 'phone', 'zone', 'advance_due', 'paid', 'outstanding'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{from: string, to: string, zone: int|null, kit: int|null, shop: int|null}  $filters
     * @return array{columns: list<string>, rows: list<list<int|float|string|null>>}
     */
    private function expenses(array $filters): array
    {
        $rows = [];

        $expenses = Expense::query()
            ->with('type')
            ->whereDate('incurred_on', '>=', $filters['from'])
            ->whereDate('incurred_on', '<=', $filters['to'])
            ->orderBy('incurred_on')
            ->orderBy('id')
            ->get();

        foreach ($expenses as $expense) {
            $rows[] = [
                $expense->incurred_on->toDateString(),
                $expense->type->name,
                $expense->amount,
                __('expenses.scopes.'.$expense->scope),
                $expense->notes,
                $expense->cancelled_at === null ? '' : ($expense->cancel_reason ?? '—'),
            ];
        }

        return [
            'columns' => ['incurred_on', 'expense_type', 'amount', 'scope', 'notes', 'cancelled'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{from: string, to: string, zone: int|null, kit: int|null, shop: int|null}  $filters
     * @return array{columns: list<string>, rows: list<list<int|float|string|null>>}
     */
    private function shops(User $user, array $filters): array
    {
        $rows = [];
        $shops = $this->dashboard->build($user, $filters)['shops'];

        foreach ($shops['summary'] ?? [] as $row) {
            $rows[] = [$row['shop'], $row['purchase'], $row['sale'], $row['advance'], $row['percent'], $row['balance']];
        }

        return [
            'columns' => ['shop', 'purchase', 'sale', 'advance', 'percent', 'balance'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{from: string, to: string, zone: int|null, kit: int|null, shop: int|null}  $filters
     * @return array{columns: list<string>, rows: list<list<int|float|string|null>>}
     */
    private function profitRows(array $filters): array
    {
        $rows = [];

        $report = $this->profits->build(['from' => $filters['from'], 'to' => $filters['to'], 'zone' => $filters['zone'], 'scope' => 'all', 'group' => 'week']);

        foreach ($report['rows'] as $row) {
            $rows[] = [
                $row['label'], $row['orders'], $row['sale'], $row['theoretical_margin'], $row['actual_purchase'],
                $row['logistics'], $row['collected'], $row['real_margin'], $row['gap'],
            ];
        }

        return [
            'columns' => ['week', 'orders', 'sale', 'theoretical_margin', 'actual_purchase', 'logistics', 'collected', 'real_margin', 'gap'],
            'rows' => $rows,
        ];
    }
}
