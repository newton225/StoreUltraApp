<?php

namespace App\Http\Requests;

use App\Models\Kit;
use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderBatchRequest extends FormRequest
{
    /**
     * Whoever may create an order may create them for a locality.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Order::class);
    }

    /**
     * Messages that say what to do, in the language of the user (playbook UI-41).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'locality.required' => __('orders.batch.errors.locality_required'),
            'kit_id.required' => __('orders.plans.errors.kit_required'),
            'kit_id.integer' => __('orders.plans.errors.kit_required'),
            'kit_id.exists' => __('orders.plans.errors.kit_invalid'),
            'kits_count.required' => __('orders.plans.errors.kits_min'),
            'kits_count.integer' => __('orders.plans.errors.kits_min'),
            'kits_count.min' => __('orders.plans.errors.kits_min'),
            'kits_count.max' => __('orders.plans.errors.kits_max'),
            'delivery_week.required' => __('orders.batch.errors.week_required'),
            'delivery_week.date' => __('orders.batch.errors.week_required'),
            'advance_due.required' => __('orders.plans.errors.advance_required'),
            'advance_due.integer' => __('orders.plans.errors.advance_required'),
            'advance_due.min' => __('orders.plans.errors.advance_min'),
            'client_ids.required' => __('orders.batch.errors.clients_required'),
            'client_ids.min' => __('orders.batch.errors.clients_required'),
            'client_ids.max' => __('orders.batch.errors.clients_max'),
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'locality' => ['required', 'string', 'max:160'],
            'kit_id' => ['required', 'integer', Rule::exists(Kit::class, 'id')->where('active', true)],
            'kits_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'delivery_week' => ['required', 'date'],
            'advance_due' => ['required', 'integer', 'min:0'],
            'client_ids' => ['required', 'array', 'min:1', 'max:60'],
            'client_ids.*' => ['integer'],
            'issue_proformas' => ['nullable', 'boolean'],
        ];
    }
}
