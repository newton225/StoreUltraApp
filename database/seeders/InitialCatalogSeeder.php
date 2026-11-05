<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Seeder;

class InitialCatalogSeeder extends Seeder
{
    /**
     * The shops and products of the workbook, with their unit purchase prices (FCFA).
     * Product 13 of the workbook was an empty placeholder and is not imported.
     *
     * @var array<int, array{string, int, string}>
     */
    private const PRODUCTS = [
        ['Barre de fer à béton de 8', 1775, 'Boutique 1'],
        ['Tôle ondulée ordinaire', 2800, 'Boutique 1'],
        ['Chevron 6/4', 2300, 'Boutique 1'],
        ['Ciment CPA 42,5 (sac de 50 kg)', 4600, 'Boutique 1'],
        ['Coude PVC Ø 110', 1300, 'Boutique 1'],
        ['Tuyau PVC Ø 110', 6000, 'Boutique 1'],
        ['Tuyau PVC Ø 75 (1/2 barre = 3 m)', 2000, 'Boutique 1'],
        ['Coude PVC Ø 75', 800, 'Boutique 1'],
        ['Té PVC Ø 75', 800, 'Boutique 1'],
        ['Collier de fixation tuyau PVC Ø 75', 800, 'Boutique 1'],
        ['Porte métallique', 22000, 'Boutique 1'],
        ['SATOPAN simple', 5000, 'Boutique 2'],
    ];

    /**
     * Seed the catalog. Running it again changes nothing.
     */
    public function run(): void
    {
        $shops = collect(['Boutique 1', 'Boutique 2'])
            ->mapWithKeys(fn (string $name): array => [$name => Shop::firstOrCreate(['name' => $name])]);

        foreach (self::PRODUCTS as [$name, $purchasePrice, $shopName]) {
            $product = Product::firstOrCreate(
                ['name' => $name],
                ['unit' => 'u', 'shop_id' => $shops[$shopName]->id],
            );

            if ($product->prices()->doesntExist()) {
                $product->prices()->create([
                    'shop_id' => $product->shop_id,
                    'purchase_price' => $purchasePrice,
                    'effective_from' => today(),
                ]);
            }
        }
    }
}
