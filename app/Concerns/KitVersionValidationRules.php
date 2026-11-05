<?php

namespace App\Concerns;

use App\Models\KitVersion;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait KitVersionValidationRules
{
    /**
     * Get the validation rules used to validate the content of a kit version.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function versionRules(): array
    {
        return [
            'sale_price_mode' => ['required', Rule::in([KitVersion::MODE_LINES, KitVersion::MODE_FIXED])],
            'fixed_sale_price' => ['nullable', 'required_if:sale_price_mode,'.KitVersion::MODE_FIXED, 'integer', 'min:0'],
            'valid_from' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(Product::class, 'id')->whereNull('archived_at'),
            ],
            'items.*.quantity_per_kit' => ['required', 'integer', 'min:1'],
            'items.*.sale_price_per_kit' => ['nullable', 'required_if:sale_price_mode,'.KitVersion::MODE_LINES, 'integer', 'min:0'],
        ];
    }
}
