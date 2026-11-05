<?php

namespace App\Http\Requests;

use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollectAdvancesRequest extends FormRequest
{
    /**
     * A group action is for the office.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage-orders');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'zone_id' => ['required', 'integer', Rule::exists(Zone::class, 'id')],
            'payment_method_id' => ['required', 'integer', Rule::exists(PaymentMethod::class, 'id')->where('active', true)],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'payments' => ['required', 'array', 'min:1', 'max:60'],
            'payments.*.order_id' => ['required', 'integer'],
            'payments.*.amount' => ['required', 'integer', 'min:1'],
            'payments.*.paid_on' => ['required', 'date', 'before_or_equal:today'],
            'payments.*.collected_by' => ['nullable', 'integer', Rule::exists(User::class, 'id')->where('is_active', true)],
            'payments.*.payer_name' => ['nullable', 'string', 'max:120'],
            'payments.*.payer_phone' => ['nullable', 'string', 'max:30'],
            'payments.*.reference' => [
                Rule::requiredIf(fn (): bool => (bool) PaymentMethod::find($this->integer('payment_method_id'))?->requires_reference),
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }
}
