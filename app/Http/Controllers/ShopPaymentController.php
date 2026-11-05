<?php

namespace App\Http\Controllers;

use App\Actions\Purchases\RecordShopPayment;
use App\Exceptions\InvalidPayment;
use App\Http\Requests\StoreShopPaymentRequest;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ShopPaymentController extends Controller
{
    /**
     * Record a payment made to a shop, or money given back by it.
     */
    public function store(StoreShopPaymentRequest $request, Shop $shop, RecordShopPayment $record): RedirectResponse
    {
        try {
            $record->handle(
                shop: $shop,
                kind: $request->validated('kind'),
                amount: (int) $request->validated('amount'),
                method: PaymentMethod::findOrFail($request->integer('payment_method_id')),
                reference: $request->validated('reference'),
                paidOn: CarbonImmutable::parse($request->validated('paid_on')),
                user: $request->user(),
                purchase: $request->filled('purchase_order_id') ? PurchaseOrder::findOrFail($request->integer('purchase_order_id')) : null,
                notes: $request->validated('notes'),
            );
        } catch (InvalidPayment $exception) {
            return back()->withErrors([$exception->field => __($exception->messageKey)])->withInput();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('purchases.payment_recorded')]);

        return back();
    }
}
