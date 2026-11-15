<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\Zone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliverKitsForZoneRequest extends FormRequest
{
    /**
     * A group action is for the office.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage-orders') && $this->user()->can('orders.record_delivery');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'zone_id' => ['required', 'integer', Rule::exists(Zone::class, 'id')],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'delivered_on' => ['required', 'date', 'before_or_equal:today'],
            'agent_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')->where('role', 'agent')->where('is_active', true)],
            'deliveries' => ['required', 'array', 'min:1', 'max:60'],
            'deliveries.*.order_id' => ['required', 'integer'],
            'deliveries.*.kits' => ['required', 'integer', 'min:1'],
            'deliveries.*.received_by' => ['required', 'string', 'max:120'],
        ];
    }
}
