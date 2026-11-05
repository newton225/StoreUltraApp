<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(Product::class)->ignore($product instanceof Product ? $product->id : null)],
            'unit' => ['required', 'string', 'max:20'],
            'shop_id' => ['nullable', 'integer', Rule::exists(Shop::class, 'id')],
        ];
    }
}
