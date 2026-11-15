<?php

namespace App\Http\Controllers;

use App\Actions\Orders\ApplyOrderDiscount;
use App\Exceptions\InvalidOrderDiscount;
use App\Http\Requests\OrderDiscountRequest;
use App\Models\Order;
use App\Support\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrderDiscountController extends Controller
{
    /**
     * Grant a discount on an order.
     */
    public function store(OrderDiscountRequest $request, Order $order, ApplyOrderDiscount $discount): RedirectResponse
    {
        try {
            $discount->handle($order, $request->integer('amount'), $request->string('reason')->toString(), $request->user());
        } catch (InvalidOrderDiscount $exception) {
            return back()->withErrors(['amount' => __($exception->messageKey, ['max' => MoneyFormatter::format($exception->maximum, app()->getLocale())])]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('orders.discount.granted')]);

        return back();
    }
}
