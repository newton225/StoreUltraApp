<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CollectAdvancesForZone;
use App\Http\Requests\CollectAdvancesRequest;
use App\Support\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrderAdvancePaymentController extends Controller
{
    /**
     * Record the advance payment of the picked orders of a zone (sub-prefecture), in one go.
     */
    public function store(CollectAdvancesRequest $request, CollectAdvancesForZone $batch): RedirectResponse
    {
        $result = $batch->handle(
            $request->integer('zone_id'),
            array_values(array_map(fn (array $row): array => [
                'order_id' => (int) $row['order_id'],
                'amount' => (int) $row['amount'],
                'payment_method_id' => (int) $row['payment_method_id'],
                'paid_on' => (string) $row['paid_on'],
                'collected_by' => ! empty($row['collected_by']) ? (int) $row['collected_by'] : null,
                'reference' => $row['reference'] ?? null,
                'payer_name' => $row['payer_name'] ?? null,
                'payer_phone' => $row['payer_phone'] ?? null,
            ], $request->validated('payments'))),
            $request->validated('idempotency_key'),
            $request->user(),
        );

        Inertia::flash('toast', $result['recorded'] === 0
            ? ['type' => 'warning', 'message' => __('orders.collect.none')]
            : ['type' => $result['failed'] > 0 ? 'warning' : 'success', 'message' => __('orders.collect.done', [
                'recorded' => $result['recorded'],
                'total' => MoneyFormatter::format($result['total'], app()->getLocale()),
                'failed' => $result['failed'],
            ])]);

        return back();
    }
}
