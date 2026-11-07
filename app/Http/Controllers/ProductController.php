<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\Shop;
use App\Support\ListSort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    private const PER_PAGE = 30;

    /**
     * List the products with their shop and current price.
     */
    public function index(Request $request): Response
    {
        $showArchived = $request->boolean('archived');
        $search = trim($request->string('search')->toString());
        $sort = ListSort::resolve($request, Product::SORTABLE);

        return Inertia::render('products/index', [
            'products' => Product::query()
                ->when(! $showArchived, fn ($query) => $query->notArchived())
                ->when($search !== '', fn ($query) => $query->search($search))
                ->with(['shop:id,name', 'currentPrice'])
                ->sortedBy($sort['sort'], $sort['direction'])
                ->paginate(self::PER_PAGE, ['id', 'name', 'unit', 'shop_id', 'archived_at'])
                ->withQueryString(),
            'showArchived' => $showArchived,
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    /**
     * Show the form creating a product with its first price.
     */
    public function create(): Response
    {
        return Inertia::render('products/create', [
            'shops' => $this->shops(),
            'today' => today()->toDateString(),
        ]);
    }

    /**
     * Create a product and record its first price in the history.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        Gate::authorize('catalog.products');

        $product = DB::transaction(function () use ($request): Product {
            $product = Product::create($request->safe()->only(['name', 'unit', 'shop_id']));

            $product->prices()->create([
                'shop_id' => $product->shop_id,
                'purchase_price' => $request->validated('purchase_price'),
                'sale_price' => $request->validated('sale_price'),
                'effective_from' => $request->validated('effective_from') ?? today(),
                'created_by' => $request->user()->id,
            ]);

            return $product;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('catalog.products.created')]);

        return to_route('products.edit', $product);
    }

    /**
     * Show a product with its full price history.
     */
    public function edit(Product $product): Response
    {
        return Inertia::render('products/edit', [
            'product' => $product->only(['id', 'name', 'unit', 'shop_id', 'archived_at']),
            'shops' => $this->shops(),
            'prices' => $product->prices()->get(['id', 'purchase_price', 'sale_price', 'effective_from']),
            'today' => today()->toDateString(),
        ]);
    }

    /**
     * Update the details of a product. Prices are never touched here.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        Gate::authorize('catalog.products');

        $product->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('catalog.products.updated')]);

        return to_route('products.index');
    }

    /**
     * Archive a product: it leaves new compositions but stays in past orders.
     */
    public function archive(Product $product): RedirectResponse
    {
        $product->forceFill(['archived_at' => now()])->save();

        return back();
    }

    /**
     * Bring an archived product back.
     */
    public function restore(Product $product): RedirectResponse
    {
        $product->forceFill(['archived_at' => null])->save();

        return back();
    }

    /**
     * @return Collection<int, Shop>
     */
    private function shops()
    {
        return Shop::query()->orderBy('name')->get(['id', 'name']);
    }
}
