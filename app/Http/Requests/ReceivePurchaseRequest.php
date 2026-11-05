<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceivePurchaseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string|Closure>
     */
    public function rules(): array
    {
        /** @var PurchaseOrder $purchase */
        $purchase = $this->route('purchaseOrder');
        $items = $purchase->items()->get()->keyBy('id');

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct', Rule::in($items->keys()->all())],
            'items.*.quantity_received' => [
                'required',
                'integer',
                'min:0',
                function (string $attribute, mixed $value, Closure $fail) use ($items): void {
                    $index = explode('.', $attribute)[1];
                    $item = $items->get($this->input("items.{$index}.id"));

                    if ($item === null) {
                        return;
                    }

                    if ($value > $item->quantity_ordered) {
                        $fail(__('purchases.errors.above_ordered'));
                    } elseif ($value < $item->quantity_received) {
                        $fail(__('purchases.errors.below_received'));
                    }
                },
            ],
            'items.*.unit_price_actual' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
