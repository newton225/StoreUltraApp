<?php

namespace App\Http\Controllers;

use App\Actions\Orders\GenerateDueOrders;
use App\Http\Requests\StoreSupplyPlanRequest;
use App\Http\Requests\UpdateSupplyPlanRequest;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\SupplyPlan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SupplyPlanController extends Controller
{
    /**
     * Set up what a client receives at each cycle, and create the orders already due.
     */
    public function store(StoreSupplyPlanRequest $request, Client $client, GenerateDueOrders $generate): RedirectResponse
    {
        $plan = new SupplyPlan(['client_id' => $client->id, ...$request->validated()]);
        $plan->forceFill(['status' => 'active', 'created_by' => $request->user()->id])->save();

        AuditLog::record('supply_plan.created', $plan, [], $plan->only([
            'client_id', 'kit_id', 'kits_per_cycle', 'advance_amount', 'starts_on', 'ends_on', 'cycles_count',
        ]) + ['frequency' => $plan->frequency->value]);

        $generate->handle(only: $plan);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('orders.plan_created')]);

        return to_route('clients.show', $client);
    }

    /**
     * Change a plan or pause, resume or end it. Orders already made keep what they were.
     */
    public function update(UpdateSupplyPlanRequest $request, SupplyPlan $plan): RedirectResponse
    {
        $before = $plan->only(['status', 'kits_per_cycle', 'advance_amount', 'ends_on', 'cycles_count']);

        $plan->fill($request->safe()->except(['status']));

        if ($request->validated('status') !== null) {
            $plan->forceFill(['status' => $request->validated('status')]);
        }

        $changed = $plan->getDirty();
        $plan->save();

        AuditLog::record(
            'supply_plan.updated',
            $plan,
            array_intersect_key($before, $changed),
            $changed,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('orders.plan_updated')]);

        return to_route('clients.show', $plan->client_id);
    }
}
