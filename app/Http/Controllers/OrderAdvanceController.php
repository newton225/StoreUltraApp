<?php

namespace App\Http\Controllers;

use App\Actions\Orders\ReceiveAdvancesForLocality;
use App\Http\Requests\ReceiveAdvancesRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrderAdvanceController extends Controller
{
    /**
     * Record the step "advance received" for the picked orders of a locality, in one go.
     */
    public function store(ReceiveAdvancesRequest $request, ReceiveAdvancesForLocality $batch): RedirectResponse
    {
        $result = $batch->handle(
            $request->validated('locality'),
            array_map('intval', $request->validated('order_ids')),
            $request->user(),
        );

        Inertia::flash('toast', $result['recorded'] === 0 && $result['failed'] === 0
            ? ['type' => 'warning', 'message' => __('orders.advances.none')]
            : ['type' => $result['failed'] > 0 ? 'warning' : 'success', 'message' => __('orders.advances.done', $result)]);

        return back();
    }
}
