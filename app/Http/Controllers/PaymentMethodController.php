<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentMethodRequest;
use App\Models\PaymentMethod;
use App\Support\CodeFromLabel;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    /**
     * List the payment methods.
     */
    public function index(): Response
    {
        return Inertia::render('payment-methods/index', [
            'methods' => PaymentMethod::query()->orderBy('label_fr')->get(['id', 'code', 'label_fr', 'label_en', 'requires_reference', 'active']),
        ]);
    }

    /**
     * Show the form creating a payment method.
     */
    public function create(): Response
    {
        return Inertia::render('payment-methods/create');
    }

    /**
     * Create a payment method.
     */
    public function store(PaymentMethodRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['code'] ??= CodeFromLabel::make($data['label_fr'], fn (string $code): bool => PaymentMethod::query()->where('code', $code)->exists());

        PaymentMethod::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('payments.methods.created')]);

        return to_route('payment-methods.index');
    }

    /**
     * Show the form editing a payment method.
     */
    public function edit(PaymentMethod $paymentMethod): Response
    {
        return Inertia::render('payment-methods/edit', [
            'method' => $paymentMethod->only(['id', 'code', 'label_fr', 'label_en', 'requires_reference', 'active']),
        ]);
    }

    /**
     * Update a payment method. Its code never changes, because old payments refer to it.
     */
    public function update(PaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->update($request->safe()->except(['code']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('payments.methods.updated')]);

        return to_route('payment-methods.index');
    }
}
