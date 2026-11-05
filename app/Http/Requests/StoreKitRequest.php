<?php

namespace App\Http\Requests;

use App\Concerns\KitVersionValidationRules;
use App\Models\Kit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKitRequest extends FormRequest
{
    use KitVersionValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name_fr' => ['required', 'string', 'max:255', Rule::unique(Kit::class)],
            'name_en' => ['required', 'string', 'max:255'],
            ...$this->versionRules(),
        ];
    }
}
