<?php

namespace App\Http\Requests;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\Profile;
use App\Models\User;
use App\Models\Zone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string|Closure>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');
        $editsSelf = $target->is($this->user());

        return [
            ...$this->profileRules($target->id),
            'profile_id' => [
                'required_without:role',
                'nullable',
                'integer',
                Rule::exists(Profile::class, 'id'),
                function (string $attribute, mixed $value, Closure $fail) use ($target, $editsSelf): void {
                    if ($editsSelf && (int) $value !== $target->effectiveProfile()->id) {
                        $fail(__('users.errors.own_role'));
                    }
                },
            ],
            'role' => [
                'required_without:profile_id',
                'nullable',
                Rule::enum(UserRole::class),
                function (string $attribute, mixed $value, Closure $fail) use ($target, $editsSelf): void {
                    if ($editsSelf && $value !== $target->role->value) {
                        $fail(__('users.errors.own_role'));
                    }
                },
            ],
            'is_active' => [
                'required',
                'boolean',
                function (string $attribute, mixed $value, Closure $fail) use ($editsSelf): void {
                    if ($editsSelf && ! filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        $fail(__('users.errors.own_status'));
                    }
                },
            ],
            'zone_ids' => ['nullable', 'array'],
            'zone_ids.*' => ['integer', Rule::exists(Zone::class, 'id')],
        ];
    }
}
