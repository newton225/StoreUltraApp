<?php

namespace App\Http\Requests;

use App\Enums\PermissionAction;
use App\Models\AuditLog;
use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CancelOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canDo(PermissionAction::OrdersCancel);
    }

    /**
     * A group cancellation always asks for the reason and for the password of the person doing it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'orders' => ['required', 'array', 'min:1', 'max:500'],
            'orders.*' => ['integer', 'distinct'],
            'reason' => ['required', 'string', 'max:500'],
            'password' => ['required', 'string', 'current_password'],
        ];
    }

    /**
     * A wrong password is traced on the first order of the group (playbook SEC-15).
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('password') && $this->filled('password')) {
                $first = Order::query()->visibleTo($this->user())->whereKey($this->input('orders', []))->orderBy('id')->first();

                if ($first !== null) {
                    AuditLog::record('order.step_password_failed', $first, [], [], $this->user());
                }
            }
        }];
    }
}
