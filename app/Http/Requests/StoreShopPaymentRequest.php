<?php

namespace App\Http\Requests;

use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\ShopPayment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopPaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Shop $shop */
        $shop = $this->route('shop');

        return [
            'kind' => ['required', Rule::in([ShopPayment::KIND_PAYMENT, ShopPayment::KIND_REFUND])],
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method_id' => ['required', 'integer', Rule::exists(PaymentMethod::class, 'id')->where('active', true)],
            'reference' => [
                Rule::requiredIf(fn (): bool => (bool) PaymentMethod::find($this->integer('payment_method_id'))?->requires_reference),
                'nullable',
                'string',
                'max:100',
            ],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'purchase_order_id' => ['nullable', 'integer', Rule::exists(PurchaseOrder::class, 'id')->where('shop_id', $shop->id)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
