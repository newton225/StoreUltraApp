<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\CreateOrdersForLocality;
use App\Actions\Orders\IssueProformasForLocality;
use App\Actions\Orders\ReceiveAdvancesForLocality;
use App\Enums\ClientStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Exceptions\OrderPricesMissing;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Client;
use App\Models\Delivery;
use App\Models\Document;
use App\Models\ExpenseAllocation;
use App\Models\Invoice;
use App\Models\Kit;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\BusinessSettings;
use App\Support\ListSort;
use App\Support\OrderProfit;
use App\Support\WeekCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    private const PER_PAGE = 25;

    /**
     * List the orders the user may see, newest week first.
     */
    public function index(Request $request, WeekCalendar $calendar, IssueProformasForLocality $proformas, ReceiveAdvancesForLocality $advances): Response
    {
        $user = $request->user();
        $search = trim($request->string('search')->toString());
        $status = OrderStatus::tryFrom($request->string('status')->toString());
        $week = $request->date('week');
        $sort = ListSort::resolve($request, Order::SORTABLE);

        $orders = Order::query()
            ->visibleTo($user)
            ->with('client:id,name,reference')
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $inner->whereLike('reference', $like)
                    ->orWhereHas('client', fn ($clients) => $clients->whereLike('name', $like)->orWhereLike('reference', $like));
            }))
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->when($week !== null, fn ($query) => $query->whereDate('delivery_week', $calendar->startOf($week)))
            ->sortedBy($sort['sort'], $sort['direction'])
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Order $order): array => $this->listed($order));

        return Inertia::render('orders/index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'status' => $status?->value,
                'week' => $week === null ? null : $calendar->startOf($week)->toDateString(),
                ...$sort,
            ],
            'statuses' => array_column(OrderStatus::cases(), 'value'),
            'canCreate' => $user->can('create', Order::class),
            'proformaLocalities' => $user->can('manage-orders') ? $proformas->localities($user) : [],
            'advanceLocalities' => $user->can('manage-orders') ? $advances->localities($user) : [],
        ]);
    }

    /**
     * Show the form creating an order by hand.
     */
    public function create(Request $request, BusinessSettings $settings, CreateOrdersForLocality $batch): Response
    {
        Gate::authorize('create', Order::class);

        $mode = $request->query('mode') === 'locality' ? 'locality' : 'single';
        $locality = trim($request->string('locality')->toString());
        $batchWeek = $request->filled('batch_week') ? $request->date('batch_week') : null;

        return Inertia::render('orders/create', [
            'clients' => Client::query()
                ->where('status', ClientStatus::Active->value)
                ->orderBy('name')
                ->get(['id', 'name', 'reference']),
            'kits' => Kit::query()->where('active', true)->orderBy('name_fr')->get()->map(fn (Kit $kit): array => ['id' => $kit->id, 'name' => $kit->name]),
            'defaults' => [
                'kits_count' => $settings->get('kits_per_client'),
                'advance_due' => $settings->get('advance_amount'),
                'delivery_week' => today()->toDateString(),
            ],
            'mode' => $mode,
            'localities' => $mode === 'locality' ? $batch->localities($request->user()) : [],
            'locality' => $locality,
            'batchWeek' => $batchWeek?->toDateString(),
            'candidates' => $mode === 'locality' && $locality !== '' ? $batch->candidates($locality, $batchWeek, $request->user()) : [],
        ]);
    }

    /**
     * Create an order by hand with the kit version in effect for its week.
     */
    public function store(StoreOrderRequest $request, CreateOrder $createOrder, WeekCalendar $calendar): RedirectResponse
    {
        $week = $calendar->startOf(CarbonImmutable::parse($request->validated('delivery_week')));
        $kit = Kit::findOrFail($request->integer('kit_id'));
        $version = $kit->versionForWeek($week);

        try {
            $order = $createOrder->handle(
                client: Client::findOrFail($request->integer('client_id')),
                version: $version ?? throw OrderPricesMissing::forKit(null),
                kitsCount: (int) $request->validated('kits_count'),
                deliveryWeek: $week,
                advanceAmount: (int) $request->validated('advance_due'),
                author: $request->user(),
                notes: $request->validated('notes'),
            );
        } catch (OrderPricesMissing) {
            return back()->withErrors(['kit_id' => __('orders.errors.prices_missing')])->withInput();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('orders.created')]);

        return to_route('orders.show', $order);
    }

    /**
     * Show an order: its frozen lines, its totals and its history.
     */
    public function show(Order $order, BusinessSettings $settings): Response
    {
        Gate::authorize('view', $order);

        $order->load('client:id,name,reference', 'kitVersion.kit', 'items.product', 'statusChanges.user:id,name', 'payments.method', 'payments.collector:id,name', 'payments.recorder:id,name', 'deliveries.agent:id,name');

        $netPaid = $order->netPaid();
        $remainingKits = $order->remainingKits();
        $isAgent = request()->user()->isRestrictedToZones();
        $seesCosts = Gate::allows('view-profit');
        $reversedIds = $order->payments->pluck('reverses_payment_id')->filter()->all();

        return Inertia::render('orders/show', [
            'order' => [
                ...$this->listed($order),
                'advance_due' => $order->advance_due,
                // What the goods cost and what is earned stay on the server for those who may not see the profit.
                ...($seesCosts ? [
                    'total_purchase_expected' => $order->total_purchase_expected,
                    'margin_expected' => $order->total_sale - $order->total_purchase_expected,
                ] : []),
                'notes' => $order->notes,
                'kit' => ['name' => $order->kitVersion->kit->name, 'version' => $order->kitVersion->version],
                'allowed_next' => $order->status->allowedNext(),
                'items' => $order->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'product_name' => $item->product->name,
                    'unit' => $item->product->unit,
                    'quantity' => $item->quantity,
                    ...($seesCosts ? ['unit_purchase_price' => $item->unit_purchase_price] : []),
                    'line_sale_total' => $item->line_sale_total,
                ])->values(),
                'history' => $order->statusChanges->map(fn ($change): array => [
                    'id' => $change->id,
                    'from' => $change->from->value,
                    'to' => $change->to->value,
                    'reason' => $change->reason,
                    'user' => $change->user?->name,
                    'at' => $change->created_at?->toIso8601String(),
                ])->values(),
            ],
            'payments' => [
                'items' => $order->payments->sortByDesc('id')->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'receipt_number' => $payment->receipt_number,
                    'kind' => $payment->kind->value,
                    'amount' => $payment->amount,
                    'method' => $payment->method->name,
                    'method_id' => $payment->payment_method_id,
                    'reference' => $payment->reference,
                    'payer_name' => $payment->payer_name,
                    'payer_phone' => $payment->payer_phone,
                    'paid_on' => $payment->paid_on->toDateString(),
                    'collector' => $payment->collector?->name,
                    'recorder' => $payment->recorder?->name,
                    'reverses_receipt' => $payment->reverses_payment_id === null
                        ? null
                        : $order->payments->firstWhere('id', $payment->reverses_payment_id)?->receipt_number,
                    'reversed' => in_array($payment->id, $reversedIds, true),
                ])->values(),
                'net_paid' => $netPaid,
                'balance_due' => max(0, $order->total_sale - $netPaid),
                'advance_outstanding' => max(0, $order->advance_due - $netPaid),
                'methods' => PaymentMethod::query()->where('active', true)->orderBy('label_fr')->get()
                    ->map(fn (PaymentMethod $method): array => ['id' => $method->id, 'name' => $method->name, 'requires_reference' => $method->requires_reference])
                    ->values(),
                'allowed_kinds' => $this->allowedPaymentKinds($order, request()->user()->can('refund', $order)),
                'client_name' => $order->client?->name,
                'current_user_id' => request()->user()->id,
                'current_user_name' => request()->user()->name,
                'collectors' => $isAgent
                    ? []
                    : User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            ],
            'delivery' => [
                'items' => $order->deliveries->sortByDesc('id')->map(fn ($delivery): array => [
                    'id' => $delivery->id,
                    'reference' => $delivery->reference,
                    'kits_delivered' => $delivery->kits_delivered,
                    'delivered_on' => $delivery->delivered_on->toDateString(),
                    'agent' => $delivery->agent?->name,
                    'received_by' => $delivery->received_by,
                    'has_signature' => $delivery->signature_path !== null,
                    'notes' => $delivery->notes,
                ])->values(),
                'remaining_kits' => $remainingKits,
                'can_deliver' => $order->status === OrderStatus::Received && $remainingKits > 0,
                'signature_required' => (bool) $settings->get('delivery_signature_required'),
                'is_agent' => $isAgent,
                'agents' => $isAgent
                    ? []
                    : User::query()->where('role', 'agent')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            ],
            'documents' => $this->documentsFor($order, $settings),
            'profit' => Gate::allows('view-profit') ? $this->profitFor($order) : null,
            'canTransition' => request()->user()->can('transition', $order),
            'today' => today()->toDateString(),
        ]);
    }

    /**
     * What the order should have earned and what it really earned, with the costs it carries.
     *
     * @return array<string, mixed>
     */
    private function profitFor(Order $order): array
    {
        return [
            ...app(OrderProfit::class)->forOrder($order),
            'expenses' => ExpenseAllocation::query()
                ->with('expense.type')
                ->where('order_id', $order->id)
                ->orderBy('id')
                ->get()
                ->map(fn (ExpenseAllocation $allocation): array => [
                    'id' => $allocation->id,
                    'type' => $allocation->expense->type->name,
                    'amount' => $allocation->amount,
                    'incurred_on' => $allocation->expense->incurred_on->toDateString(),
                    'cancelled' => $allocation->expense->isCancelled(),
                ])
                ->values(),
        ];
    }

    /**
     * What can be issued for this order (proforma, invoice, receipts, delivery notes) and what already was.
     *
     * @return array<string, mixed>
     */
    private function documentsFor(Order $order, BusinessSettings $settings): array
    {
        $canManage = request()->user()->can('manage-orders');
        $invoice = Invoice::query()->where('order_id', $order->id)->whereNull('cancelled_at')->first();

        $subjects = [
            [Order::class, [$order->id]],
            [Payment::class, $order->payments->pluck('id')->all()],
            [Delivery::class, $order->deliveries->pluck('id')->all()],
            [Invoice::class, $invoice === null ? [] : [$invoice->id]],
        ];

        $active = Document::query()
            ->whereNull('cancelled_at')
            ->where(function ($query) use ($subjects): void {
                foreach ($subjects as [$class, $ids]) {
                    $query->orWhere(fn ($inner) => $inner->where('subject_type', $class)->whereIn('subject_id', $ids));
                }
            })
            ->get()
            ->keyBy(fn (Document $document): string => $document->type->value.'|'.$document->subject_type.'|'.$document->subject_id);

        $find = fn (string $type, string $class, ?int $id): ?array => ($document = $active->get($type.'|'.$class.'|'.$id)) === null
            ? null
            : ['id' => $document->id, 'number' => $document->number, 'locale' => $document->locale];

        $invoiceable = in_array($order->status, [OrderStatus::Delivered, OrderStatus::Settled, OrderStatus::Closed], true);

        $targets = [
            [
                'type' => 'proforma',
                'subject_id' => $order->id,
                'label' => null,
                'document' => $find('proforma', Order::class, $order->id),
                'can_issue' => $canManage && $order->status !== OrderStatus::Cancelled,
            ],
            [
                'type' => 'invoice',
                'subject_id' => $order->id,
                'label' => null,
                'document' => $find('invoice', Invoice::class, $invoice?->id),
                'can_issue' => $canManage && $invoiceable && $invoice === null,
            ],
        ];

        foreach ($order->payments as $payment) {
            $targets[] = [
                'type' => 'receipt',
                'subject_id' => $payment->id,
                'label' => $payment->receipt_number,
                'document' => $find('receipt', Payment::class, $payment->id),
                'can_issue' => true,
            ];
        }

        foreach ($order->deliveries as $delivery) {
            $targets[] = [
                'type' => 'delivery_note',
                'subject_id' => $delivery->id,
                'label' => $delivery->reference,
                'document' => $find('delivery_note', Delivery::class, $delivery->id),
                'can_issue' => true,
            ];
        }

        return [
            'targets' => $targets,
            'fne_required' => $settings->get('fne_mode') === 'required',
            'invoice' => $invoice === null ? null : [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'fne_status' => $invoice->fne_status,
                'fne_number' => $invoice->fne_number,
            ],
            'can_manage' => $canManage,
        ];
    }

    /**
     * The kinds of payment the user can record now for this order.
     *
     * @return array<int, string>
     */
    private function allowedPaymentKinds(Order $order, bool $canRefund): array
    {
        $kinds = [];

        foreach ([PaymentKind::Advance, PaymentKind::Balance] as $kind) {
            if (in_array($order->status, $kind->allowedStatuses() ?? [], true)) {
                $kinds[] = $kind->value;
            }
        }

        if ($canRefund) {
            $kinds[] = PaymentKind::Refund->value;
        }

        return $kinds;
    }

    /**
     * @return array{id: int, reference: string|null, client: array{id: int, name: string}|null, delivery_week: string, status: string, kits_count: int, total_sale: int}
     */
    private function listed(Order $order): array
    {
        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'client' => $order->client === null ? null : ['id' => $order->client->id, 'name' => $order->client->name],
            'delivery_week' => $order->delivery_week->toDateString(),
            'status' => $order->status->value,
            'kits_count' => $order->kits_count,
            'total_sale' => $order->total_sale,
        ];
    }
}
