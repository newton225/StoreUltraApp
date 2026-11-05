<?php

namespace App\Http\Requests\Settings;

use App\Support\BusinessSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessSettingsUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules built from the setting definitions.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return collect(app(BusinessSettings::class)->definitions())
            ->map(fn (array $definition): array => $this->rulesFor($definition))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int, mixed>
     */
    private function rulesFor(array $definition): array
    {
        $required = $definition['default'] !== null;

        $presence = $required ? ['required'] : ['nullable'];

        return match (true) {
            isset($definition['options']) => [...$presence, 'string', Rule::in($definition['options'])],
            $definition['type'] === 'integer' => [...$presence, 'integer', 'min:'.($definition['min'] ?? 0)],
            $definition['type'] === 'boolean' => ['required', 'boolean'],
            default => [...$presence, 'string', 'max:255'],
        };
    }
}
