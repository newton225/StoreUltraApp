<?php

namespace App\Http\Requests;

use App\Enums\SupplyPlanStatus;
use App\Models\SupplyPlan;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplyPlanRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string|Closure>
     */
    public function rules(): array
    {
        /** @var SupplyPlan $plan */
        $plan = $this->route('plan');

        return [
            'status' => [
                'sometimes',
                Rule::enum(SupplyPlanStatus::class),
                function (string $attribute, mixed $value, Closure $fail) use ($plan): void {
                    $to = SupplyPlanStatus::tryFrom((string) $value);

                    if ($to !== null && $to !== $plan->status && ! $plan->status->canBecome($to)) {
                        $fail(__('orders.errors.plan_status'));
                    }
                },
            ],
            'kits_per_cycle' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'advance_amount' => ['sometimes', 'integer', 'min:0'],
            'ends_on' => ['sometimes', 'nullable', 'date', 'after_or_equal:'.$plan->starts_on->toDateString()],
            'cycles_count' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
