<?php

namespace App\Http\Requests;

use App\Concerns\NormalizesDecimalPrices;
use App\Models\Product;
use App\Models\ProductComponent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductComponentsRequest extends FormRequest
{
    use NormalizesDecimalPrices;

    /**
     * The quantity of a component may be typed with a comma (0,5).
     */
    protected function prepareForValidation(): void
    {
        $components = $this->input('components');

        if (! is_array($components)) {
            return;
        }

        foreach ($components as $index => $component) {
            $quantity = is_array($component) ? $this->normalizedPrice($component['quantity'] ?? null) : null;

            if ($quantity !== null) {
                $components[$index]['quantity'] = $quantity;
            }
        }

        $this->merge(['components' => $components]);
    }

    /**
     * The whole composition of the product, replaced as sent (none sent makes it a plain product again).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'components' => ['nullable', 'array', 'max:30'],
            'components.*.component_product_id' => ['required', 'integer', 'distinct', Rule::exists(Product::class, 'id')->whereNull('archived_at')],
            'components.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000', 'decimal:0,3'],
        ];
    }

    /**
     * One level only: a product cannot contain itself, nor a manufactured product, and a product that is
     * already a component of another one cannot become manufactured.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Product $product */
            $product = $this->route('product');
            $ids = collect($this->input('components', []))->pluck('component_product_id')->filter()->map(fn (mixed $id): int => (int) $id);

            if ($ids->isEmpty()) {
                return;
            }

            if ($ids->contains($product->id)) {
                $validator->errors()->add('components', __('catalog.components.errors.itself'));
            }

            if (Product::query()->whereIn('id', $ids)->whereHas('components')->exists()) {
                $validator->errors()->add('components', __('catalog.components.errors.manufactured'));
            }

            if (ProductComponent::query()->where('component_product_id', $product->id)->exists()) {
                $validator->errors()->add('components', __('catalog.components.errors.is_component'));
            }
        }];
    }
}
