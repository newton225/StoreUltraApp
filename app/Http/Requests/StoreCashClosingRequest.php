<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCashClosingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'closed_on' => ['required', 'date', 'before_or_equal:today'],
            'counted_amount' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
