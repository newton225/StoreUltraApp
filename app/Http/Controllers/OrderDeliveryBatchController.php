<?php

namespace App\Http\Controllers;

use App\Actions\Orders\DeliverKitsForZone;
use App\Http\Requests\DeliverKitsForZoneRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrderDeliveryBatchController extends Controller
{
    /**
     * Record the delivery of the picked orders of a zone (sub-prefecture), in one go.
     */
    public function store(DeliverKitsForZoneRequest $request, DeliverKitsForZone $batch): RedirectResponse
    {
        $agentId = $request->integer('agent_id');

        $result = $batch->handle(
            $request->integer('zone_id'),
            array_values(array_map(fn (array $row): array => [
                'order_id' => (int) $row['order_id'],
                'kits' => (int) $row['kits'],
                'received_by' => trim((string) $row['received_by']),
            ], $request->validated('deliveries'))),
            CarbonImmutable::parse($request->validated('delivered_on')),
            $agentId > 0 ? User::query()->find($agentId) : null,
            (string) $request->validated('idempotency_key'),
            $request->user(),
        );

        Inertia::flash('toast', $result['delivered'] === 0
            ? ['type' => 'warning', 'message' => __('orders.deliver_batch.none')]
            : ['type' => $result['failed'] > 0 ? 'warning' : 'success', 'message' => trans_choice('orders.deliver_batch.done', $result['delivered'], $result)]);

        return back();
    }
}
