<?php

namespace App\Http\Requests;

use App\Concerns\NormalizesDecimalPrices;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    use NormalizesDecimalPrices;

    protected function prepareForValidation(): void
    {
        $this->normalizePriceFields(['purchase_price', 'sale_price']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(Product::class)],
            'unit' => ['required', 'string', 'max:20'],
            'pack_size' => ['sometimes', 'required', 'integer', 'min:1', 'max:100000'],
            'shop_id' => ['nullable', 'integer', Rule::exists(Shop::class, 'id')],
            'purchase_price' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'effective_from' => ['nullable', 'date'],
        ];
    }
}
