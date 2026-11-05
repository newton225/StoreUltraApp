<?php

namespace App\Http\Controllers;

use App\Actions\Payments\RecordPayment;
use App\Enums\PaymentKind;
use App\Exceptions\InvalidPayment;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Zone;
use App\Support\AdvanceArrears;
use App\Support\ListSort;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    private const PER_PAGE = 30;

    /**
     * List the payments with the amounts received and given back for the chosen filters.
     */
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $kind = PaymentKind::tryFrom($request->string('kind')->toString());
        $methodId = $request->integer('method') ?: null;
        $from = $request->date('from');
        $to = $request->date('to');
        $sort = ListSort::resolve($request, Payment::SORTABLE);

        $filtered = fn (): Builder => Payment::query()
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $inner->whereLike('receipt_number', $like)
                    ->orWhereHas('order', fn (Builder $orders) => $orders
                        ->whereLike('reference', $like)
                        ->orWhereHas('client', fn (Builder $clients) => $clients->whereLike('name', $like)));
            }))
            ->when($kind !== null, fn (Builder $query) => $query->where('kind', $kind->value))
            ->when($methodId !== null, fn (Builder $query) => $query->where('payment_method_id', $methodId))
            ->when($from !== null, fn (Builder $query) => $query->whereDate('paid_on', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->whereDate('paid_on', '<=', $to));

        $sums = $filtered()->toBase()
            ->selectRaw("coalesce(sum(case when kind <> 'refund' then amount else 0 end), 0) as received")
            ->selectRaw("coalesce(sum(case when kind = 'refund' then amount else 0 end), 0) as refunded")
            ->first();

        $received = (int) $sums->received;
        $refunded = (int) $sums->refunded;

        $payments = $filtered()
            ->with(['order:id,reference,client_id', 'order.client:id,name', 'method', 'collector:id,name', 'recorder:id,name'])
            ->sortedBy($sort['sort'], $sort['direction'])
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Payment $payment): array => [
                'id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'kind' => $payment->kind->value,
                'amount' => $payment->amount,
                'method' => $payment->method->name,
                'reference' => $payment->reference,
                'payer' => $payment->payer_name,
                'paid_on' => $payment->paid_on->toDateString(),
                'collector' => $payment->collector?->name,
                'recorder' => $payment->recorder?->name,
                'order' => ['id' => $payment->order->id, 'reference' => $payment->order->reference, 'client' => $payment->order->client?->name],
            ]);

        return Inertia::render('payments/index', [
            'payments' => $payments,
            'totals' => ['received' => $received, 'refunded' => $refunded, 'net' => $received - $refunded],
            'filters' => [
                'search' => $search,
                'kind' => $kind?->value,
                'method' => $methodId,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
                ...$sort,
            ],
            'kinds' => array_column(PaymentKind::cases(), 'value'),
            'methods' => PaymentMethod::query()->orderBy('label_fr')->get()->map(fn (PaymentMethod $method): array => ['id' => $method->id, 'name' => $method->name])->values(),
        ]);
    }

    /**
     * The orders whose advance is late: the week has started and the advance is not covered.
     */
    public function arrears(Request $request, AdvanceArrears $arrears): Response
    {
        $zoneId = $request->integer('zone') ?: null;

        return Inertia::render('payments/arrears', [
            'orders' => $arrears->page($zoneId, self::PER_PAGE),
            'total_outstanding' => $arrears->outstanding($zoneId),
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name']),
            'filters' => ['zone' => $zoneId],
        ]);
    }

    /**
     * Record a payment or a refund for an order and issue its receipt number.
     */
    public function store(StorePaymentRequest $request, Order $order, RecordPayment $record): RedirectResponse
    {
        try {
            $record->handle(
                order: $order,
                kind: PaymentKind::from($request->validated('kind')),
                amount: (int) $request->validated('amount'),
                method: PaymentMethod::findOrFail($request->integer('payment_method_id')),
                reference: $request->validated('reference'),
                paidOn: CarbonImmutable::parse($request->validated('paid_on')),
                user: $request->user(),
                reverses: $request->filled('reverses_payment_id') ? Payment::findOrFail($request->integer('reverses_payment_id')) : null,
                notes: $request->validated('notes'),
                idempotencyKey: $request->validated('idempotency_key'),
                payerName: $request->validated('payer_name'),
                payerPhone: $request->validated('payer_phone'),
                // Someone who works in the field always collects for themselves; the office may say who did.
                collectedBy: $request->user()->isRestrictedToZones() ? null : $request->validated('collected_by'),
            );
        } catch (InvalidPayment $exception) {
            return back()->withErrors([$exception->field => __($exception->messageKey)])->withInput();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('payments.recorded')]);

        return to_route('orders.show', $order);
    }
}
