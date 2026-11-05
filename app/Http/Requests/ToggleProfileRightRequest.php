<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Models\Profile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ToggleProfileRightRequest extends FormRequest
{
    /**
     * A system profile cannot be changed: only a custom one can have a right ticked or unticked.
     */
    public function authorize(): bool
    {
        $profile = $this->route('profile');

        return ! ($profile instanceof Profile && $profile->is_system);
    }

    /**
     * One right of the closed catalogue (or the restriction to the assigned zones) and whether it is granted.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'right' => ['required', 'string', Rule::in([...Permission::values(), 'restricted_to_zones'])],
            'granted' => ['required', 'boolean'],
        ];
    }
}
