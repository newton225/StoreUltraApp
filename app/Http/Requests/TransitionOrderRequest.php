<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransitionOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $this->user()->can('transition', $order);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', Rule::enum(OrderStatus::class)],
            'reason' => ['nullable', 'required_if:to,'.OrderStatus::Cancelled->value, 'string', 'max:500'],
            'password' => $this->needsPassword() ? ['required', 'string', 'current_password'] : ['nullable'],
        ];
    }

    /**
     * A wrong password on a critical step is traced (playbook SEC-15).
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('password') && $this->filled('password')) {
                /** @var Order $order */
                $order = $this->route('order');

                AuditLog::record('order.step_password_failed', $order, [], [], $this->user());
            }
        }];
    }

    /**
     * A critical step (delivered, settled, closed) and the cancellation of an order on which money was received ask for
     * the password of the person doing it.
     */
    public function needsPassword(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');
        $to = OrderStatus::tryFrom($this->string('to')->toString());

        return $to?->needsPassword() === true
            || ($to === OrderStatus::Cancelled && $order->payments()->exists());
    }
}
