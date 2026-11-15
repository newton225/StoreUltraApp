<?php

namespace App\Http\Requests;

use App\Enums\PermissionAction;
use App\Models\AuditLog;
use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class OrderDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $this->user()->can('transition', $order) && $this->user()->canDo(PermissionAction::OrdersDiscount);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'reason' => ['required', 'string', 'max:500'],
            'password' => ['required', 'string', 'current_password'],
        ];
    }

    /**
     * A discount lowers what is owed: it asks for the password, and a wrong one is traced (playbook SEC-15).
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
}
