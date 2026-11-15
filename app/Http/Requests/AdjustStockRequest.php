<?php

namespace App\Http\Requests;

use App\Concerns\NormalizesDecimalPrices;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    use NormalizesDecimalPrices;

    /**
     * The unit cost may be typed with a comma (1 775,5).
     */
    protected function prepareForValidation(): void
    {
        $this->normalizePriceFields(['unit_cost']);
    }

    /**
     * A correction always asks for its reason and for the password of the person doing it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
            'unit_cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
            'password' => ['required', 'string', 'current_password'],
        ];
    }
}
