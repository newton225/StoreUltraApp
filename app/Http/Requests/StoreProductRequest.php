<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
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
            'shop_id' => ['nullable', 'integer', Rule::exists(Shop::class, 'id')],
            'purchase_price' => ['required', 'integer', 'min:0'],
            'sale_price' => ['nullable', 'integer', 'min:0'],
            'effective_from' => ['nullable', 'date'],
        ];
    }
}
