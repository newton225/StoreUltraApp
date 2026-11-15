<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProductComponentsRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProductComponentController extends Controller
{
    /**
     * Replace the composition of a product: what a unit of it is made of. Each change is traced.
     */
    public function update(UpdateProductComponentsRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            // One by one, so that every removal and addition reaches the audit log.
            $product->components()->get()->each->delete();

            foreach ($request->validated('components') ?? [] as $component) {
                $product->components()->create([
                    'component_product_id' => (int) $component['component_product_id'],
                    'quantity' => $component['quantity'],
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('catalog.components.saved')]);

        return to_route('products.edit', $product);
    }
}
