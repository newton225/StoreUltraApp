<?php

namespace App\Http\Controllers;

use App\Actions\Orders\TransitionOrder;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Http\Requests\TransitionOrderRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrderTransitionController extends Controller
{
    /**
     * Move an order to its next step, or cancel it.
     */
    public function store(TransitionOrderRequest $request, Order $order, TransitionOrder $transition): RedirectResponse
    {
        try {
            $transition->handle(
                $order,
                OrderStatus::from($request->validated('to')),
                $request->validated('reason'),
                $request->user(),
            );
        } catch (InvalidOrderTransition $exception) {
            return back()->withErrors(['to' => __($exception->messageKey)]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('orders.status_updated')]);

        return back();
    }
}
