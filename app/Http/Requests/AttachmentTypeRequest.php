<?php

namespace App\Http\Requests;

use App\Models\AttachmentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachmentTypeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->route('attachment_type');

        return [
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', Rule::unique(AttachmentType::class)->ignore($type instanceof AttachmentType ? $type->id : null)],
            'label_fr' => ['required', 'string', 'max:255'],
            'label_en' => ['required', 'string', 'max:255'],
            'applies_to' => ['required', Rule::in(AttachmentType::KINDS)],
            'required' => ['sometimes', 'boolean'],
            'has_expiry' => ['sometimes', 'boolean'],
            'on_documents' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
