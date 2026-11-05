<?php

namespace App\Http\Requests;

use App\Models\Zone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ZoneRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $zone = $this->route('zone');
        $ignoredId = $zone instanceof Zone ? $zone->id : null;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(Zone::class)->ignore($ignoredId)],
            'code' => ['nullable', 'string', 'max:20', Rule::unique(Zone::class)->ignore($ignoredId)],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
