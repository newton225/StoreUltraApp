<?php

namespace App\Http\Requests;

use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'idempotency_key' => ['required', 'string', 'max:64'],
            'payments' => ['required', 'array', 'min:1', 'max:60'],
            'payments.*.order_id' => ['required', 'integer'],
            'payments.*.amount' => ['required', 'integer', 'min:1'],
            'payments.*.payment_method_id' => ['required', 'integer', Rule::exists(PaymentMethod::class, 'id')->where('active', true)],
            'payments.*.paid_on' => ['required', 'date', 'before_or_equal:today'],
            'payments.*.collected_by' => ['nullable', 'integer', Rule::exists(User::class, 'id')->where('is_active', true)],
            'payments.*.payer_name' => ['nullable', 'string', 'max:120'],
            'payments.*.payer_phone' => ['nullable', 'string', 'max:30'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * The transaction reference is asked by the method of each line, so it is checked line by line.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $required = PaymentMethod::query()->where('requires_reference', true)->pluck('id')->all();

                foreach ((array) $this->input('payments', []) as $index => $row) {
                    $method = (int) ($row['payment_method_id'] ?? 0);

                    if (in_array($method, $required, true) && trim((string) ($row['reference'] ?? '')) === '') {
                        $validator->errors()->add("payments.{$index}.reference", __('validation.required', ['attribute' => __('validation.attributes.payments.*.reference')]));
                    }
                }
            },
        ];
    }
}
