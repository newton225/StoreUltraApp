<?php

namespace Database\Seeders;

use App\Actions\Kits\CreateKitVersion;
use App\Models\Kit;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class InitialKitSeeder extends Seeder
{
    /**
     * The lines of the kit of the workbook: product, quantity per kit, sale price of the line (FCFA).
     *
     * @var array<int, array{string, int, int}>
     */
    private const LINES = [
        ['Barre de fer à béton de 8', 3, 9000],
        ['Tôle ondulée ordinaire', 3, 10500],
        ['Chevron 6/4', 3, 7500],
        ['Ciment CPA 42,5 (sac de 50 kg)', 7, 40200],
        ['Coude PVC Ø 110', 1, 1500],
        ['Tuyau PVC Ø 110', 1, 6500],
        ['Tuyau PVC Ø 75 (1/2 barre = 3 m)', 1, 2500],
        ['Coude PVC Ø 75', 1, 1000],
        ['Té PVC Ø 75', 1, 1000],
        ['Collier de fixation tuyau PVC Ø 75', 1, 1000],
        ['Porte métallique', 1, 25000],
        ['SATOPAN simple', 1, 6500],
    ];

    /**
     * Seed the kit of the workbook. Running it again changes nothing.
     */
    public function run(CreateKitVersion $createVersion): void
    {
        $this->call(InitialCatalogSeeder::class);

        $kit = Kit::firstOrCreate(
            ['name_fr' => 'Kit de construction'],
            ['name_en' => 'Construction kit'],
        );

        if ($kit->versions()->exists()) {
            return;
        }

        $products = Product::query()->pluck('id', 'name');

        $createVersion->handle($kit, [
            'sale_price_mode' => 'lines',
            'fixed_sale_price' => null,
            'valid_from' => today()->toDateString(),
            'items' => array_map(fn (array $line): array => [
                'product_id' => $products[$line[0]],
                'quantity_per_kit' => $line[1],
                'sale_price_per_kit' => $line[2],
            ], self::LINES),
        ], User::query()->where('role', 'owner')->first());
    }
}
