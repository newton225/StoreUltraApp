<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShopRequest;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    /**
     * List the shops.
     */
    public function index(): Response
    {
        return Inertia::render('shops/index', [
            'shops' => Shop::query()
                ->withCount('products')
                ->orderBy('name')
                ->get(['id', 'name', 'contact', 'phone', 'active']),
        ]);
    }

    /**
     * Show the form creating a shop.
     */
    public function create(): Response
    {
        return Inertia::render('shops/create');
    }

    /**
     * Create a shop.
     */
    public function store(ShopRequest $request): RedirectResponse
    {
        Shop::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('catalog.shops.created')]);

        return to_route('shops.index');
    }

    /**
     * Show the form editing a shop.
     */
    public function edit(Shop $shop): Response
    {
        return Inertia::render('shops/edit', [
            'shop' => $shop->only(['id', 'name', 'contact', 'phone', 'address', 'payment_terms', 'active']),
        ]);
    }

    /**
     * Update a shop.
     */
    public function update(ShopRequest $request, Shop $shop): RedirectResponse
    {
        $shop->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('catalog.shops.updated')]);

        return to_route('shops.index');
    }
}
