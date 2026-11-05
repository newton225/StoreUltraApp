<?php

namespace App\Http\Requests;

use App\Enums\Frequency;
use App\Models\Kit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplyPlanRequest extends FormRequest
{
    /**
     * Messages that say what to do, in the language of the user (playbook UI-41).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kit_id.required' => __('orders.plans.errors.kit_required'),
            'kit_id.integer' => __('orders.plans.errors.kit_required'),
            'kit_id.exists' => __('orders.plans.errors.kit_invalid'),
            'kits_per_cycle.required' => __('orders.plans.errors.kits_min'),
            'kits_per_cycle.integer' => __('orders.plans.errors.kits_min'),
            'kits_per_cycle.min' => __('orders.plans.errors.kits_min'),
            'kits_per_cycle.max' => __('orders.plans.errors.kits_max'),
            'advance_amount.required' => __('orders.plans.errors.advance_required'),
            'advance_amount.integer' => __('orders.plans.errors.advance_required'),
            'advance_amount.min' => __('orders.plans.errors.advance_min'),
            'frequency.required' => __('orders.plans.errors.frequency_invalid'),
            'frequency.enum' => __('orders.plans.errors.frequency_invalid'),
            'starts_on.required' => __('orders.plans.errors.starts_required'),
            'starts_on.date' => __('orders.plans.errors.starts_required'),
            'ends_on.after_or_equal' => __('orders.plans.errors.ends_after_start'),
            'cycles_count.min' => __('orders.plans.errors.cycles_min'),
            'cycles_count.integer' => __('orders.plans.errors.cycles_min'),
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
            'kit_id' => ['required', 'integer', Rule::exists(Kit::class, 'id')->where('active', true)],
            'kits_per_cycle' => ['required', 'integer', 'min:1', 'max:1000'],
            'advance_amount' => ['required', 'integer', 'min:0'],
            'frequency' => ['required', Rule::enum(Frequency::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'cycles_count' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
