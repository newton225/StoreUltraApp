<?php

namespace App\Http\Requests;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\Profile;
use App\Models\Zone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'profile_id' => ['required_without:role', 'nullable', 'integer', Rule::exists(Profile::class, 'id')],
            'role' => ['required_without:profile_id', 'nullable', Rule::enum(UserRole::class)],
            'zone_ids' => ['nullable', 'array'],
            'zone_ids.*' => ['integer', Rule::exists(Zone::class, 'id')],
        ];
    }
}
