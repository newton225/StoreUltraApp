<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRestockPurchaseRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer', Rule::exists(Shop::class, 'id')],
            'lines' => ['required', 'array', 'min:1', 'max:60'],
            'lines.*.product_id' => ['required', 'integer', 'distinct', Rule::exists(Product::class, 'id')->whereNull('archived_at')],
            'lines.*.packs' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /**
     * Only products of the shop that are bought as they are can be restocked from it.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ids = collect($this->input('lines'))->pluck('product_id')->map(fn (mixed $id): int => (int) $id);

            $valid = Product::query()
                ->whereKey($ids)
                ->where('shop_id', (int) $this->input('shop_id'))
                ->whereDoesntHave('components')
                ->count();

            if ($valid !== $ids->count()) {
                $validator->errors()->add('lines', __('purchases.restock.errors.products'));
            }
        }];
    }
}
