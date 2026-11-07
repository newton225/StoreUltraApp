<?php

namespace App\Http\Controllers;

use App\Actions\Purchases\CancelPurchaseOrder;
use App\Actions\Purchases\GeneratePurchaseOrders;
use App\Actions\Purchases\ReceivePurchaseOrder;
use App\Actions\Purchases\SendPurchaseOrder;
use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\InvalidPurchaseOperation;
use App\Http\Requests\GeneratePurchaseOrdersRequest;
use App\Http\Requests\ReceivePurchaseRequest;
use App\Models\Document;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopPayment;
use App\Support\AdvanceAllocation;
use App\Support\ShopAccounts;
use App\Support\WeekCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    private const PER_PAGE = 25;

    /**
     * List the purchase orders of a week, with how the advances are split and what each shop is owed.
     */
    public function index(Request $request, WeekCalendar $calendar, ShopAccounts $accounts): Response
    {
        $week = $calendar->startOf($request->date('week') ?? now());
        $shopId = $request->integer('shop') ?: null;
        $status = PurchaseOrderStatus::tryFrom($request->string('status')->toString());

        $purchaseOrders = PurchaseOrder::query()
            ->with('shop:id,name')
            ->whereDate('week', $week)
            ->when($shopId !== null, fn (Builder $query) => $query->where('shop_id', $shopId))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status->value))
            ->orderBy('shop_id')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (PurchaseOrder $purchase): array => [
                'id' => $purchase->id,
                'reference' => $purchase->reference,
                'shop' => ['id' => $purchase->shop->id, 'name' => $purchase->shop->name],
                'week' => $purchase->week->toDateString(),
                'status' => $purchase->status->value,
                'expected_total' => $purchase->expected_total,
                'actual_total' => $purchase->actual_total,
            ]);

        $pending = OrderItem::query()
            ->whereNull('purchase_order_id')
            ->whereHas('order', fn (Builder $orders) => $orders
                ->where('status', OrderStatus::AdvanceReceived->value)
                ->whereDate('delivery_week', $week));

        return Inertia::render('purchases/index', [
            'purchaseOrders' => $purchaseOrders,
            'week' => $week->toDateString(),
            'weeks' => $calendar->known($request->user(), $week),
            'filters' => ['shop' => $shopId, 'status' => $status?->value],
            'statuses' => array_column(PurchaseOrderStatus::cases(), 'value'),
            'shops' => Shop::query()->orderBy('name')->get(['id', 'name']),
            'pending_lines' => (clone $pending)->whereNotNull('shop_id')->count(),
            'unassigned_lines' => (clone $pending)->whereNull('shop_id')->count(),
            'summary' => $this->weekSummary($week),
            'accounts' => $accounts->all(),
        ]);
    }

    /**
     * Group the needs of a week into purchase orders.
     */
    public function generate(GeneratePurchaseOrdersRequest $request, GeneratePurchaseOrders $generate, WeekCalendar $calendar): RedirectResponse
    {
        $week = CarbonImmutable::parse($request->validated('week'));
        $result = $generate->handle($week, $request->user());

        Inertia::flash('toast', [
            'type' => $result['unassigned'] > 0 ? 'warning' : 'success',
            'message' => __('purchases.generated', $result),
        ]);

        return to_route('purchases.index', ['week' => $calendar->startOf($week)->toDateString()]);
    }

    /**
     * Show a purchase order with its lines, the gaps between ordered and received, and its payments.
     */
    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load('shop:id,name', 'items.product', 'payments.method', 'payments.payer:id,name');

        $status = $purchaseOrder->status;

        return Inertia::render('purchases/show', [
            'purchase' => [
                'id' => $purchaseOrder->id,
                'reference' => $purchaseOrder->reference,
                'shop' => ['id' => $purchaseOrder->shop->id, 'name' => $purchaseOrder->shop->name],
                'week' => $purchaseOrder->week->toDateString(),
                'status' => $status->value,
                'sent_at' => $purchaseOrder->sent_at?->toIso8601String(),
                'expected_total' => $purchaseOrder->expected_total,
                'actual_total' => $purchaseOrder->actual_total,
                'orders_count' => $purchaseOrder->orderItems()->distinct()->count('order_id'),
                'items' => $purchaseOrder->items->map(fn (PurchaseOrderItem $item): array => [
                    'id' => $item->id,
                    'product_name' => $item->product->name,
                    'unit' => $item->product->unit,
                    'quantity_ordered' => $item->quantity_ordered,
                    'quantity_received' => $item->quantity_received,
                    'shortfall' => $item->quantity_ordered - $item->quantity_received,
                    'unit_price_expected' => $item->unit_price_expected,
                    'unit_price_actual' => $item->unit_price_actual,
                    'price_variance' => $item->unit_price_actual === null ? 0 : $item->unit_price_actual - $item->unit_price_expected,
                ])->values(),
                'payments' => $purchaseOrder->payments->sortByDesc('id')->map(fn (ShopPayment $payment): array => [
                    'id' => $payment->id,
                    'kind' => $payment->kind,
                    'amount' => $payment->amount,
                    'method' => $payment->method->name,
                    'reference' => $payment->reference,
                    'paid_on' => $payment->paid_on->toDateString(),
                    'payer' => $payment->payer?->name,
                ])->values(),
                'can_send' => $status === PurchaseOrderStatus::Draft && Gate::allows('purchases.send'),
                'can_cancel' => $status === PurchaseOrderStatus::Draft && Gate::allows('purchases.cancel'),
                'can_receive' => $status->canReceive() && Gate::allows('purchases.receive'),
                'document' => ($document = Document::query()
                    ->where('type', 'purchase_order')
                    ->where('subject_type', PurchaseOrder::class)
                    ->where('subject_id', $purchaseOrder->id)
                    ->whereNull('cancelled_at')
                    ->first()) === null ? null : ['id' => $document->id, 'number' => $document->number],
            ],
            'methods' => PaymentMethod::query()->where('active', true)->orderBy('label_fr')->get()
                ->map(fn (PaymentMethod $method): array => ['id' => $method->id, 'name' => $method->name, 'requires_reference' => $method->requires_reference])
                ->values(),
            'today' => today()->toDateString(),
        ]);
    }

    /**
     * Mark a draft as sent to the shop.
     */
    public function send(Request $request, PurchaseOrder $purchaseOrder, SendPurchaseOrder $send): RedirectResponse
    {
        return $this->run(fn () => $send->handle($purchaseOrder, $request->user()), 'purchases.sent');
    }

    /**
     * Record the goods received.
     */
    public function receive(ReceivePurchaseRequest $request, PurchaseOrder $purchaseOrder, ReceivePurchaseOrder $receive): RedirectResponse
    {
        $lines = [];

        foreach ($request->validated('items') as $line) {
            $lines[] = [
                'id' => (int) $line['id'],
                'quantity_received' => (int) $line['quantity_received'],
                'unit_price_actual' => isset($line['unit_price_actual']) ? (int) $line['unit_price_actual'] : null,
            ];
        }

        return $this->run(fn () => $receive->handle($purchaseOrder, $lines, $request->user()), 'purchases.received');
    }

    /**
     * Cancel a draft.
     */
    public function cancel(Request $request, PurchaseOrder $purchaseOrder, CancelPurchaseOrder $cancel): RedirectResponse
    {
        return $this->run(fn () => $cancel->handle($purchaseOrder, $request->user()), 'purchases.cancelled');
    }

    /**
     * Run an action and turn its refusal into a form error.
     */
    private function run(callable $action, string $successKey): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidPurchaseOperation $exception) {
            return back()->withErrors([$exception->field => __($exception->messageKey)]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __($successKey)]);

        return back();
    }

    /**
     * The advances received for the week, split between the shops in proportion to their purchases.
     *
     * @return array{advances: int, shops: array<int, array{shop_id: int, name: string, purchase: int, allocated_advance: int, to_finance: int}>}
     */
    private function weekSummary(CarbonImmutable $week): array
    {
        $advances = Order::query()
            ->whereDate('delivery_week', $week)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->get()
            ->sum(fn (Order $order): int => $order->netPaid());

        $purchases = PurchaseOrder::query()
            ->with('shop:id,name')
            ->whereDate('week', $week)
            ->where('status', '!=', PurchaseOrderStatus::Cancelled->value)
            ->get()
            ->groupBy('shop_id');

        $weights = $purchases->map(fn ($group): int => (int) $group->sum('expected_total'))->all();
        $shares = AdvanceAllocation::split((int) $advances, $weights);

        $rows = $purchases->map(fn ($group, $shopId): array => [
            'shop_id' => (int) $shopId,
            'name' => $group->first()->shop->name,
            'purchase' => $weights[$shopId],
            'allocated_advance' => $shares[$shopId],
            'to_finance' => $weights[$shopId] - $shares[$shopId],
        ])->sortBy('name')->values()->all();

        return ['advances' => (int) $advances, 'shops' => $rows];
    }
}
