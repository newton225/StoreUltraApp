<?php

namespace App\Http\Requests;

use App\Models\Delivery;
use App\Models\ExpenseType;
use App\Models\Order;
use App\Models\Shop;
use App\Models\Zone;
use App\Rules\SafeUpload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'expense_type_id' => ['required', 'integer', Rule::exists(ExpenseType::class, 'id')->where('active', true)],
            'amount' => ['required', 'integer', 'min:1'],
            // Marking a cost as paid from the cash box writes a cash movement: only who manages the cash box may.
            'paid_from_cash' => ['nullable', 'boolean', Rule::prohibitedIf(fn (): bool => ! $this->user()?->can('manage-cash'))],
            'incurred_on' => ['required', 'date', 'before_or_equal:today'],
            'scope' => ['required', Rule::in(['order', 'delivery', 'zone', 'shop', 'week'])],
            'order_id' => ['required_if:scope,order', 'nullable', 'integer', Rule::exists(Order::class, 'id')],
            'delivery_id' => ['required_if:scope,delivery', 'nullable', 'integer', Rule::exists(Delivery::class, 'id')],
            'zone_id' => ['required_if:scope,zone', 'nullable', 'integer', Rule::exists(Zone::class, 'id')],
            'shop_id' => ['required_if:scope,shop', 'nullable', 'integer', Rule::exists(Shop::class, 'id')],
            'week' => ['required_if:scope,zone,shop,week', 'nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'proof' => ['nullable', 'bail', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096', new SafeUpload],
        ];
    }
}
