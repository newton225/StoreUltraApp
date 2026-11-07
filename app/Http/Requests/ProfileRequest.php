<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\PermissionAction;
use App\Models\Profile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    /**
     * A system profile cannot be changed: only a custom one can be updated.
     */
    public function authorize(): bool
    {
        $profile = $this->route('profile');

        return ! ($profile instanceof Profile && $profile->is_system);
    }

    /**
     * Get the validation rules that apply to the request. A profile is made of permissions of the closed
     * catalogue and of nothing else.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $profile = $this->route('profile');
        $ignoredId = $profile instanceof Profile ? $profile->id : null;

        return [
            'label_fr' => ['required', 'string', 'max:80', Rule::unique(Profile::class, 'label_fr')->ignore($ignoredId)],
            'label_en' => ['required', 'string', 'max:80'],
            'restricted_to_zones' => ['required', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in([...Permission::values(), ...PermissionAction::values()])],
        ];
    }
}
