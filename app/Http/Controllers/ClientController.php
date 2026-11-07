<?php

namespace App\Http\Controllers;

use App\Enums\ClientStatus;
use App\Http\Requests\ClientStatusRequest;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Models\Kit;
use App\Models\Order;
use App\Models\SupplyPlan;
use App\Models\User;
use App\Models\Zone;
use App\Support\BusinessSettings;
use App\Support\ListSort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    private const PER_PAGE = 25;

    /**
     * List the clients the user may see, with search, filters and the number of places used.
     */
    public function index(Request $request, BusinessSettings $settings): Response
    {
        Gate::authorize('viewAny', Client::class);

        $user = $request->user();
        $search = trim($request->string('search')->toString());
        $status = ClientStatus::tryFrom($request->string('status')->toString());
        $zoneId = $request->integer('zone') ?: null;
        $sort = ListSort::resolve($request, Client::SORTABLE);

        $clients = Client::query()
            ->visibleTo($user)
            ->with('zone:id,name')
            ->when($search !== '', fn ($query) => $query->search($search))
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->when($zoneId !== null, fn ($query) => $query->where('zone_id', $zoneId))
            ->sortedBy($sort['sort'], $sort['direction'])
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Client $client): array => $this->listed($client));

        return Inertia::render('clients/index', [
            'clients' => $clients,
            'filters' => ['search' => $search, 'status' => $status?->value, 'zone' => $zoneId, ...$sort],
            'zones' => $this->zonesFor($user, includeInactive: true),
            'statuses' => array_column(ClientStatus::cases(), 'value'),
            'capacity' => $user->isRestrictedToZones() ? null : $this->capacity($settings),
        ]);
    }

    /**
     * Show the registration form.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Client::class);

        $user = $request->user();

        return Inertia::render('clients/create', [
            'zones' => $this->zonesFor($user),
            'statuses' => $user->isRestrictedToZones() ? [] : array_column(ClientStatus::cases(), 'value'),
        ]);
    }

    /**
     * Register a client. A field agent's client waits for the office to confirm it.
     */
    public function store(StoreClientRequest $request, BusinessSettings $settings): RedirectResponse
    {
        $user = $request->user();
        $restricted = $user->isRestrictedToZones();

        $client = new Client($request->safe()->except(['status']));
        $client->forceFill([
            'status' => $restricted ? ClientStatus::Pending : ($request->validated('status') ?? ClientStatus::Active),
            'created_by' => $user->id,
        ])->save();

        $client->forceFill(['reference' => sprintf('CLI-%04d', $client->id)])->save();

        $capacity = $this->capacity($settings);

        Inertia::flash('toast', $capacity !== null && $capacity['exceeded']
            ? ['type' => 'warning', 'message' => __('clients.cap_exceeded', ['count' => $capacity['count'], 'cap' => $capacity['cap']])]
            : ['type' => 'success', 'message' => __('clients.created')]);

        return to_route('clients.show', $client);
    }

    /**
     * Show the fiche of a client.
     */
    public function show(Request $request, Client $client, BusinessSettings $settings): Response
    {
        Gate::authorize('view', $client);

        $client->load('zone:id,name', 'creator:id,name', 'members');

        $canManageOrders = $request->user()->can('manage-orders') && $request->user()->can('orders.plans');

        return Inertia::render('clients/show', [
            'client' => [
                ...$this->listed($client),
                'locality' => $client->locality,
                'delivery_address' => $client->delivery_address,
                'contact_person' => $client->contact_person,
                'notes' => $client->notes,
                'contact_trade' => $client->members->first()?->trade,
                'created_by' => $client->creator?->name,
                'created_at' => $client->created_at?->toDateString(),
            ],
            'plans' => $client->supplyPlans()->with('kit')->latest('id')->get()->map(fn (SupplyPlan $plan): array => [
                'id' => $plan->id,
                'kit_name' => $plan->kit->name,
                'kits_per_cycle' => $plan->kits_per_cycle,
                'advance_amount' => $plan->advance_amount,
                'frequency' => $plan->frequency->value,
                'starts_on' => $plan->starts_on->toDateString(),
                'ends_on' => $plan->ends_on?->toDateString(),
                'cycles_count' => $plan->cycles_count,
                'status' => $plan->status->value,
            ])->values(),
            'orders' => $client->orders()->orderByDesc('delivery_week')->orderByDesc('id')->limit(10)->get()->map(fn (Order $order): array => [
                'id' => $order->id,
                'reference' => $order->reference,
                'delivery_week' => $order->delivery_week->toDateString(),
                'status' => $order->status->value,
                'kits_count' => $order->kits_count,
                'total_sale' => $order->total_sale,
            ])->values(),
            'canChangeStatus' => ! $request->user()->isRestrictedToZones() && $request->user()->can('update', $client),
            'statuses' => array_column(ClientStatus::cases(), 'value'),
            'canManageOrders' => $canManageOrders,
            'planDefaults' => $canManageOrders ? [
                'kits_per_cycle' => $settings->get('kits_per_client'),
                'advance_amount' => $settings->get('advance_amount'),
                'frequency' => $settings->get('delivery_frequency'),
                'starts_on' => today()->toDateString(),
            ] : null,
            'kits' => $canManageOrders
                ? Kit::query()->where('active', true)->orderBy('name_fr')->get()->map(fn (Kit $kit): array => ['id' => $kit->id, 'name' => $kit->name])->values()
                : [],
        ]);
    }

    /**
     * Change only the status of a client. The change is written to the audit journal by the model.
     */
    public function status(ClientStatusRequest $request, Client $client): RedirectResponse
    {
        $client->forceFill(['status' => $request->validated('status')])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('clients.status_changed', ['status' => __('clients.statuses.'.$client->status->value)])]);

        return back();
    }

    /**
     * Show the form editing a client.
     */
    public function edit(Request $request, Client $client): Response
    {
        Gate::authorize('update', $client);

        $user = $request->user();

        return Inertia::render('clients/edit', [
            'client' => [
                ...$client->only(['id', 'reference', 'commercial_number', 'name', 'phone', 'zone_id', 'locality', 'delivery_address', 'contact_person', 'notes']),
                'status' => $client->status->value,
            ],
            'zones' => $this->zonesFor($user, currentZoneId: $client->zone_id),
            'statuses' => $user->isRestrictedToZones() ? [] : array_column(ClientStatus::cases(), 'value'),
        ]);
    }

    /**
     * Update a client. Only the office changes the status.
     */
    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->fill($request->safe()->except(['status']));

        if ($request->validated('status') !== null) {
            $client->forceFill(['status' => $request->validated('status')]);
        }

        $client->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('clients.updated')]);

        return to_route('clients.show', $client);
    }

    /**
     * @return array{id: int, reference: string|null, commercial_number: string|null, name: string, phone: string, status: string, zone: array{id: int, name: string}|null}
     */
    private function listed(Client $client): array
    {
        return [
            'id' => $client->id,
            'reference' => $client->reference,
            'commercial_number' => $client->commercial_number,
            'name' => $client->name,
            'phone' => $client->phone,
            'status' => $client->status->value,
            'zone' => $client->zone === null ? null : ['id' => $client->zone->id, 'name' => $client->zone->name],
        ];
    }

    /**
     * The zones the user can pick from: all for the office, their own for a field agent.
     *
     * @return Collection<int, Zone>
     */
    private function zonesFor(User $user, bool $includeInactive = false, ?int $currentZoneId = null): Collection
    {
        return Zone::query()
            ->when($user->isRestrictedToZones(), fn ($query) => $query->whereIn('id', $user->zones()->select('zones.id')))
            ->when(! $includeInactive, fn ($query) => $query->where(
                fn ($inner) => $inner->where('active', true)->orWhere('id', $currentZoneId),
            ))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * How many places of the client cap are used, or null when there is no cap (the default: there is
     * no limit to the number of clients). Going over a cap warns but never blocks.
     *
     * @return array{count: int, cap: int, exceeded: bool}|null
     */
    private function capacity(BusinessSettings $settings): ?array
    {
        $cap = (int) $settings->get('client_cap');

        if ($cap === 0) {
            return null;
        }

        $count = Client::query()->countingTowardsCap()->count();

        return ['count' => $count, 'cap' => $cap, 'exceeded' => $count > $cap];
    }
}
