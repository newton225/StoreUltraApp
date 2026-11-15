<?php

namespace App\Http\Controllers;

use App\Actions\Stock\AdjustStock;
use App\Enums\PermissionAction;
use App\Exceptions\InvalidStockMovement;
use App\Http\Requests\AdjustStockRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\Search;
use App\Support\StockLevels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    private const MOVEMENTS = 100;

    /**
     * The stock of every product that is bought as it is: what is on hand, what a unit cost on average,
     * and what it is worth. A manufactured product has no stock of its own (its components do).
     */
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        $products = Product::query()
            ->whereDoesntHave('components')
            ->where(fn (Builder $query) => $query->whereNull('archived_at')->orWhereHas('stock', fn (Builder $stock) => $stock->where('quantity_on_hand', '>', 0)))
            ->when($search !== '', fn (Builder $query) => Search::contains($query, 'name', $search))
            ->with('stock')
            ->orderBy('name')
            ->get();

        $items = $products->map(function (Product $product): array {
            $stock = $product->stock;
            $onHand = $stock->quantity_on_hand ?? 0;
            $average = $stock->average_cost ?? 0;

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit,
                'pack_size' => $product->pack_size,
                'on_hand' => $onHand,
                'average_cost' => $average,
                'value' => (int) round($onHand * $average),
            ];
        })->values();

        return Inertia::render('stock/index', [
            'items' => $items,
            'totalValue' => (int) $items->sum('value'),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * One product: its stock and its latest movements.
     */
    public function show(Request $request, Product $product, StockLevels $levels): Response
    {
        return Inertia::render('stock/show', [
            'product' => $product->only(['id', 'name', 'unit', 'pack_size']),
            'levels' => $levels->forProduct($product->id),
            'movements' => StockMovement::query()
                ->where('product_id', $product->id)
                ->with('user:id,name')
                ->orderByDesc('id')
                ->limit(self::MOVEMENTS)
                ->get()
                ->map(fn (StockMovement $movement): array => [
                    'id' => $movement->id,
                    'type' => $movement->type->value,
                    'quantity' => $movement->quantity,
                    'unit_cost' => $movement->unit_cost,
                    'reason' => $movement->reason,
                    'user' => $movement->user?->name,
                    'at' => $movement->created_at->toIso8601String(),
                ])->values(),
            'canAdjust' => $request->user()->can(PermissionAction::StockAdjust->value),
        ]);
    }

    /**
     * Correct the stock of a product by hand: a signed quantity, a reason, the password of the person.
     */
    public function adjust(AdjustStockRequest $request, Product $product, AdjustStock $adjust): RedirectResponse
    {
        try {
            $adjust->handle(
                $product,
                (int) $request->validated('quantity'),
                $request->validated('unit_cost') === null ? null : (float) $request->validated('unit_cost'),
                (string) $request->validated('reason'),
                $request->user(),
            );
        } catch (InvalidStockMovement $exception) {
            return back()->withErrors([$exception->field => __($exception->messageKey)])->withInput();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('stock.adjust.done')]);

        return back();
    }
}
