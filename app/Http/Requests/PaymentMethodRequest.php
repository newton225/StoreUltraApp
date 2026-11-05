<?php

namespace App\Http\Requests;

use App\Models\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentMethodRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $method = $this->route('payment_method');

        return [
            'code' => ['nullable', 'string', 'max:30', 'regex:/^[a-z0-9_]+$/', Rule::unique(PaymentMethod::class)->ignore($method instanceof PaymentMethod ? $method->id : null)],
            'label_fr' => ['required', 'string', 'max:255'],
            'label_en' => ['required', 'string', 'max:255'],
            'requires_reference' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
