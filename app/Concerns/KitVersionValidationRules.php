<?php

namespace App\Concerns;

use App\Models\KitVersion;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait KitVersionValidationRules
{
    use NormalizesDecimalPrices;

    /**
     * Prices may be typed with a decimal comma or point (12,5 or 12.5) and are kept as typed.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizePriceFields(['fixed_sale_price']);

        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $index => $item) {
            $price = is_array($item) ? $this->normalizedPrice($item['sale_price_per_kit'] ?? null) : null;

            if ($price !== null) {
                $items[$index]['sale_price_per_kit'] = $price;
            }
        }

        $this->merge(['items' => $items]);
    }

    /**
     * Get the validation rules used to validate the content of a kit version.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function versionRules(): array
    {
        return [
            'sale_price_mode' => ['required', Rule::in([KitVersion::MODE_LINES, KitVersion::MODE_FIXED])],
            'fixed_sale_price' => ['nullable', 'required_if:sale_price_mode,'.KitVersion::MODE_FIXED, 'numeric', 'decimal:0,2', 'min:0'],
            'valid_from' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(Product::class, 'id')->whereNull('archived_at')->where('sellable', true),
            ],
            'items.*.quantity_per_kit' => ['required', 'integer', 'min:1'],
            'items.*.sale_price_per_kit' => ['nullable', 'required_if:sale_price_mode,'.KitVersion::MODE_LINES, 'numeric', 'decimal:0,2', 'min:0'],
        ];
    }
}
