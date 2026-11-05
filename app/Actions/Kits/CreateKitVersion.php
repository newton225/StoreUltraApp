<?php

namespace App\Actions\Kits;

use App\Models\Kit;
use App\Models\KitVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateKitVersion
{
    /**
     * Record a new version of a kit. Earlier versions are left as they were.
     *
     * @param  array<string, mixed>  $data  The validated sale_price_mode, fixed_sale_price, valid_from and items.
     */
    public function handle(Kit $kit, array $data, ?User $author = null): KitVersion
    {
        return DB::transaction(function () use ($kit, $data, $author): KitVersion {
            $version = $kit->versions()->create([
                'version' => ((int) KitVersion::query()->where('kit_id', $kit->id)->max('version')) + 1,
                'sale_price_mode' => $data['sale_price_mode'],
                'fixed_sale_price' => $data['sale_price_mode'] === KitVersion::MODE_FIXED ? ($data['fixed_sale_price'] ?? null) : null,
                'valid_from' => $data['valid_from'],
                'created_by' => $author?->id,
            ]);

            foreach ($data['items'] as $item) {
                $version->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity_per_kit' => $item['quantity_per_kit'],
                    'sale_price_per_kit' => $item['sale_price_per_kit'] ?? null,
                ]);
            }

            return $version;
        });
    }
}
