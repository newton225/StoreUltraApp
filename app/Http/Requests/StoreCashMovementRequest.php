<?php

namespace App\Http\Requests;

use App\Models\CashMovement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashMovementRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in([CashMovement::IN, CashMovement::OUT])],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
            'label' => ['required', 'string', 'max:160'],
            'counterparty' => ['nullable', 'string', 'max:120'],
            'document_reference' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
