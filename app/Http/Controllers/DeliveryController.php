<?php

namespace App\Http\Controllers;

use App\Actions\Deliveries\RecordDelivery;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidDelivery;
use App\Exceptions\InvalidPayment;
use App\Http\Requests\StoreDeliveryRequest;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Zone;
use App\Support\ListSort;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryController extends Controller
{
    private const PER_PAGE = 25;

    /**
     * The work of the deliveries: the orders waiting for their kits, and the deliveries already made.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $zoneId = $request->integer('zone') ?: null;
        $sort = ListSort::resolve($request, Delivery::SORTABLE);

        $toDeliver = Order::query()
            ->visibleTo($user)
            ->where('status', OrderStatus::Received->value)
            ->withSum('deliveries as delivered_kits', 'kits_delivered')
            ->with('client.zone')
            ->when($zoneId !== null, fn (Builder $query) => $query->whereHas('client', fn (Builder $clients) => $clients->where('zone_id', $zoneId)))
            ->orderBy('delivery_week')
            ->orderBy('id')
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'reference' => $order->reference,
                'client' => $order->client->name,
                'phone' => $order->client->phone,
                'zone' => $order->client->zone->name,
                'delivery_week' => $order->delivery_week->toDateString(),
                'kits_count' => $order->kits_count,
                'remaining_kits' => $order->kits_count - (int) $order->getAttribute('delivered_kits'),
            ])
            ->values();

        $deliveries = Delivery::query()
            ->whereHas('order', fn (Builder $orders) => $orders->visibleTo($user)
                ->when($zoneId !== null, fn (Builder $inner) => $inner->whereHas('client', fn (Builder $clients) => $clients->where('zone_id', $zoneId))))
            ->with(['order.client:id,name', 'agent:id,name'])
            ->sortedBy($sort['sort'], $sort['direction'])
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Delivery $delivery): array => [
                'id' => $delivery->id,
                'reference' => $delivery->reference,
                'order' => ['id' => $delivery->order->id, 'reference' => $delivery->order->reference, 'client' => $delivery->order->client?->name],
                'kits_delivered' => $delivery->kits_delivered,
                'delivered_on' => $delivery->delivered_on->toDateString(),
                'agent' => $delivery->agent?->name,
                'received_by' => $delivery->received_by,
                'has_signature' => $delivery->signature_path !== null,
            ]);

        return Inertia::render('deliveries/index', [
            'sort' => $sort,
            'to_deliver' => $toDeliver,
            'deliveries' => $deliveries,
            'zones' => Zone::query()
                ->when($user->isRestrictedToZones(), fn (Builder $query) => $query->whereIn('id', $user->zones()->select('zones.id')))
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => ['zone' => $zoneId],
        ]);
    }

    /**
     * Record kits handed over to the client, with the signature of the person who received them.
     */
    public function store(StoreDeliveryRequest $request, Order $order, RecordDelivery $record): RedirectResponse
    {
        $user = $request->user();
        $agent = $user->isRestrictedToZones()
            ? $user
            : ($request->filled('agent_id') ? User::find($request->integer('agent_id')) : null);

        $payment = $request->filled('collect_amount')
            ? [
                'amount' => $request->integer('collect_amount'),
                'method' => PaymentMethod::findOrFail($request->integer('payment_method_id')),
                'reference' => $request->validated('reference'),
            ]
            : null;

        try {
            $record->handle(
                order: $order,
                kits: $request->integer('kits_delivered'),
                deliveredOn: CarbonImmutable::parse($request->validated('delivered_on')),
                receivedBy: $request->validated('received_by'),
                signature: StoreDeliveryRequest::decodeSignature($request->validated('signature')),
                agent: $agent,
                notes: $request->validated('notes'),
                user: $user,
                payment: $payment,
                idempotencyKey: $request->validated('idempotency_key'),
            );
        } catch (InvalidDelivery $exception) {
            return back()->withErrors([$exception->field => __($exception->messageKey)])->withInput();
        } catch (InvalidPayment $exception) {
            $field = $exception->field === 'amount' ? 'collect_amount' : $exception->field;

            return back()->withErrors([$field => __($exception->messageKey)])->withInput();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('deliveries.recorded')]);

        return to_route('orders.show', $order);
    }

    /**
     * Serve the signature image to whoever can see the order. It is never public.
     */
    public function signature(Delivery $delivery): StreamedResponse
    {
        Gate::authorize('view', $delivery->order);

        abort_if($delivery->signature_path === null, 404);

        AuditLog::record('delivery.signature_opened', $delivery, [], ['reference' => $delivery->reference]);

        return Storage::disk('local')->response($delivery->signature_path, null, ['Content-Type' => 'image/png']);
    }
}
