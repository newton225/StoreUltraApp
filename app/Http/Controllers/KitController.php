<?php

namespace App\Http\Controllers;

use App\Actions\Kits\CreateKitVersion;
use App\Http\Requests\StoreKitRequest;
use App\Http\Requests\UpdateKitRequest;
use App\Models\Client;
use App\Models\Kit;
use App\Models\KitVersion;
use App\Models\Product;
use App\Support\BusinessSettings;
use App\Support\KitPricing;
use App\Support\KitSummary;
use App\Support\ProductPricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class KitController extends Controller
{
    /**
     * List the kits with the price of their current version.
     */
    public function index(KitPricing $pricing): Response
    {
        $kits = Kit::query()
            ->with('currentVersion.items.product.currentPrice')
            ->orderBy('name_fr')
            ->get();

        $seesCosts = Gate::allows('view-profit');

        return Inertia::render('kits/index', [
            'seesCosts' => $seesCosts,
            'kits' => $kits->map(fn (Kit $kit): array => [
                'id' => $kit->id,
                'name' => $kit->name,
                'active' => $kit->active,
                'current_version' => $kit->currentVersion === null ? null : [
                    'id' => $kit->currentVersion->id,
                    'version' => $kit->currentVersion->version,
                    'valid_from' => $kit->currentVersion->valid_from->toDateString(),
                    'summary' => $this->summary($pricing->summary($kit->currentVersion), $seesCosts),
                ],
            ])->values(),
        ]);
    }

    /**
     * Show the form creating a kit with its first version.
     */
    public function create(Request $request): Response
    {
        $products = $this->products();

        return Inertia::render('kits/create', [
            'products' => $products,
            'today' => today()->toDateString(),
            'copy' => $this->copyOf($request->integer('from'), $products),
        ]);
    }

    /**
     * Create a kit and record its first version.
     */
    public function store(StoreKitRequest $request, CreateKitVersion $createVersion): RedirectResponse
    {
        Gate::authorize('catalog.kits');

        $kit = Kit::create($request->safe()->only(['name_fr', 'name_en']));

        $createVersion->handle($kit, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('kits.created')]);

        return to_route('kits.show', $kit);
    }

    /**
     * Show a kit with all its versions and what each one costs and sells for.
     */
    public function show(Kit $kit, KitPricing $pricing, BusinessSettings $settings): Response
    {
        $kit->load('versions.items.product.currentPrice');

        $seesCosts = Gate::allows('view-profit');

        return Inertia::render('kits/show', [
            'seesCosts' => $seesCosts,
            'kit' => $kit->only(['id', 'name_fr', 'name_en', 'active']),
            'versions' => $kit->versions->map(fn (KitVersion $version): array => [
                'id' => $version->id,
                'version' => $version->version,
                'valid_from' => $version->valid_from->toDateString(),
                'sale_price_mode' => $version->sale_price_mode,
                'fixed_sale_price' => $version->fixed_sale_price,
                'items' => $version->items->map(fn ($item): array => [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'unit' => $item->product->unit,
                    'quantity_per_kit' => $item->quantity_per_kit,
                    'sale_price_per_kit' => $item->sale_price_per_kit,
                    'unit_purchase_price' => $seesCosts ? app(ProductPricing::class)->purchasePrice($item->product) : null,
                ])->values(),
                'summary' => $this->summary($pricing->summary($version), $seesCosts),
            ])->values(),
            'currentVersionId' => $kit->currentVersion()->value('kit_versions.id'),
            'products' => $this->products(),
            'kitsPerClient' => $settings->get('kits_per_client'),
            'clientCap' => $this->clientsToPlanFor($settings),
            'today' => today()->toDateString(),
        ]);
    }

    /**
     * Rename a kit or deactivate it. The content changes through new versions only.
     */
    public function update(UpdateKitRequest $request, Kit $kit): RedirectResponse
    {
        Gate::authorize('catalog.kits');

        $kit->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('kits.updated')]);

        return to_route('kits.show', $kit);
    }

    /**
     * The number of clients the price summary of a kit is worked out for: the client cap when there is
     * one, otherwise the clients there are now (at least one).
     */
    /**
     * The price summary of a kit as sent to the screen. What a kit costs and what it earns are the profit:
     * they go only to who may see it (`view-profit`), the others get the sale price.
     *
     * @return array{purchase: int|float|null, sale: int|float|null, margin: int|float|null, complete: bool, missing_purchase_prices: array<int, int>}
     */
    private function summary(KitSummary $summary, bool $seesCosts): array
    {
        $data = $summary->toArray();

        if ($seesCosts) {
            return $data;
        }

        return [...$data, 'purchase' => null, 'margin' => null, 'missing_purchase_prices' => []];
    }

    /**
     * What a copy of a kit starts from: its names, and the composition and price mode of its current
     * version. Nothing is saved: the person adjusts the form, then creates a new kit. Products that were
     * archived since are left out and counted, so the screen can say so.
     *
     * @param  Collection<int, Product>  $products
     * @return array{source: string, name_fr: string, name_en: string, mode: string, fixed_price: int|float|null, lines: array<int, array{product_id: int, quantity_per_kit: int, sale_price_per_kit: int|float|null}>, skipped: int}|null
     */
    private function copyOf(int $kitId, Collection $products): ?array
    {
        $kit = $kitId > 0 ? Kit::query()->with('currentVersion.items')->find($kitId) : null;
        $version = $kit?->currentVersion;

        if ($kit === null || $version === null) {
            return null;
        }

        $available = $products->pluck('id')->all();
        $items = $version->items->filter(fn ($item): bool => in_array($item->product_id, $available, true));

        return [
            'source' => $kit->name,
            'name_fr' => trans('kits.copy.name', ['name' => $kit->name_fr], 'fr'),
            'name_en' => trans('kits.copy.name', ['name' => $kit->name_en], 'en'),
            'mode' => $version->sale_price_mode,
            'fixed_price' => $version->fixed_sale_price,
            'lines' => $items->map(fn ($item): array => [
                'product_id' => $item->product_id,
                'quantity_per_kit' => $item->quantity_per_kit,
                'sale_price_per_kit' => $item->sale_price_per_kit,
            ])->values()->all(),
            'skipped' => $version->items->count() - $items->count(),
        ];
    }

    private function clientsToPlanFor(BusinessSettings $settings): int
    {
        $cap = (int) $settings->get('client_cap');

        return $cap > 0 ? $cap : max(1, Client::query()->countingTowardsCap()->count());
    }

    /**
     * The products a new composition can use.
     *
     * @return Collection<int, Product>
     */
    private function products(): Collection
    {
        return Product::query()->notArchived()->orderBy('name')->get(['id', 'name', 'unit']);
    }
}
