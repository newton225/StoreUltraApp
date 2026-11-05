<?php

namespace App\Http\Requests;

use App\Enums\PaymentKind;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    /**
     * Whoever can see the order can take a payment for it; only the office gives money back.
     */
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');
        $user = $this->user();

        if (! $user->can('view', $order)) {
            return false;
        }

        return $this->input('kind') !== PaymentKind::Refund->value || $user->can('refund', $order);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(PaymentKind::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method_id' => ['required', 'integer', Rule::exists(PaymentMethod::class, 'id')->where('active', true)],
            'reference' => [
                Rule::requiredIf(fn (): bool => (bool) PaymentMethod::find($this->integer('payment_method_id'))?->requires_reference),
                'nullable',
                'string',
                'max:100',
            ],
            'payer_name' => ['nullable', 'string', 'max:120'],
            'payer_phone' => ['nullable', 'string', 'max:30'],
            'collected_by' => ['nullable', 'integer', Rule::exists(User::class, 'id')->where('is_active', true)],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'reverses_payment_id' => ['nullable', 'integer', Rule::exists(Payment::class, 'id')],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
