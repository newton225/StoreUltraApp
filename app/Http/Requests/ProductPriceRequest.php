<?php

namespace App\Http\Requests;

use App\Concerns\NormalizesDecimalPrices;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductPriceRequest extends FormRequest
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
            'purchase_price' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'effective_from' => ['required', 'date'],
        ];
    }
}
