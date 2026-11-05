<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CreateOrdersForZone;
use App\Exceptions\OrderPricesMissing;
use App\Http\Requests\StoreOrderBatchRequest;
use App\Models\Kit;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrderBatchController extends Controller
{
    /**
     * Create the order of a week for the TPE of a zone (sub-prefecture), in one go, and issue their proformas when asked.
     */
    public function store(StoreOrderBatchRequest $request, CreateOrdersForZone $batch): RedirectResponse
    {
        $week = CarbonImmutable::parse($request->validated('delivery_week'));

        try {
            $result = $batch->handle(
                zoneId: (int) $request->validated('zone_id'),
                kit: Kit::findOrFail($request->integer('kit_id')),
                kitsCount: (int) $request->validated('kits_count'),
                week: $week,
                advance: (int) $request->validated('advance_due'),
                clientIds: array_values(array_map('intval', $request->validated('client_ids'))),
                issueProformas: $request->boolean('issue_proformas'),
                locale: app()->getLocale(),
                user: $request->user(),
            );
        } catch (OrderPricesMissing) {
            return back()->withErrors(['kit_id' => __('orders.errors.prices_missing')])->withInput();
        }

        Inertia::flash('toast', [
            'type' => $result['created'] === 0 || $result['failed'] > 0 || $result['proformas_failed'] > 0 ? 'warning' : 'success',
            'message' => $this->message($result),
        ]);

        return to_route('orders.index', ['week' => $week->toDateString()]);
    }

    /**
     * What happened, in a sentence that only names what is not zero.
     *
     * @param  array{created: int, skipped: int, failed: int, proformas: int, proformas_failed: int}  $result
     */
    private function message(array $result): string
    {
        $parts = [$result['created'] > 0 ? trans_choice('orders.batch.toast.created', $result['created']) : __('orders.batch.toast.none')];

        foreach (['proformas', 'proformas_failed', 'skipped', 'failed'] as $key) {
            if ($result[$key] > 0) {
                $parts[] = trans_choice("orders.batch.toast.{$key}", $result[$key]);
            }
        }

        return implode(' · ', $parts);
    }
}
