<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductPriceRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProductPriceController extends Controller
{
    /**
     * Add a price to the history of a product. Earlier prices stay as they were.
     */
    public function store(ProductPriceRequest $request, Product $product): RedirectResponse
    {
        $product->prices()->create([
            ...$request->validated(),
            'shop_id' => $product->shop_id,
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('catalog.prices.created')]);

        return to_route('products.edit', $product);
    }
}
