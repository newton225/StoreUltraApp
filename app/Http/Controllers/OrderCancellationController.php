<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CancelOrders;
use App\Http\Requests\CancelOrdersRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrderCancellationController extends Controller
{
    /**
     * Cancel, in one go, the orders ticked in the list.
     */
    public function store(CancelOrdersRequest $request, CancelOrders $cancel): RedirectResponse
    {
        $result = $cancel->handle(
            array_map('intval', $request->validated('orders')),
            $request->validated('reason'),
            $request->user(),
        );

        Inertia::flash('toast', $result['cancelled'] === 0 || $result['skipped'] > 0
            ? ['type' => 'warning', 'message' => __('orders.cancel_batch.done_partial', $result)]
            : ['type' => 'success', 'message' => __('orders.cancel_batch.done', $result)]);

        return back();
    }
}
