<?php

namespace App\Http\Requests;

use App\Models\ExpenseType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseTypeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->route('expense_type');

        return [
            'code' => ['nullable', 'string', 'max:30', 'regex:/^[a-z0-9_]+$/', Rule::unique(ExpenseType::class)->ignore($type instanceof ExpenseType ? $type->id : null)],
            'label_fr' => ['required', 'string', 'max:255'],
            'label_en' => ['required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
