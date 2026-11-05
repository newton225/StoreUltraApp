<?php

namespace App\Http\Requests;

use App\Models\Kit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKitRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $kit = $this->route('kit');

        return [
            'name_fr' => ['required', 'string', 'max:255', Rule::unique(Kit::class)->ignore($kit instanceof Kit ? $kit->id : null)],
            'name_en' => ['required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
