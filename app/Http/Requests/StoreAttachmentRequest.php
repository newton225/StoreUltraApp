<?php

namespace App\Http\Requests;

use App\Models\Attachment;
use App\Models\AttachmentType;
use App\Rules\SafeUpload;
use App\Support\AttachmentAlerts;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAttachmentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attachment_type_id' => ['required', 'integer', Rule::exists(AttachmentType::class, 'id')->where('active', true)],
            'owner_id' => ['nullable', 'integer'],
            'number' => ['required', 'string', 'max:100'],
            'issued_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'confirm_duplicate' => ['nullable', 'boolean'],
            'file' => ['nullable', 'bail', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096', new SafeUpload],
        ];
    }

    /**
     * Whether the owner already has a document of this type with the same number.
     */
    private function alreadyKept(AttachmentType $type): bool
    {
        return Attachment::query()
            ->where('attachment_type_id', $type->id)
            ->where('number', $this->string('number')->trim()->toString())
            ->when(
                $type->applies_to === 'company',
                fn ($query) => $query->whereNull('owner_id'),
                fn ($query) => $query->where('owner_id', $this->integer('owner_id')),
            )
            ->exists();
    }

    /**
     * The owner must exist for the kind of the type: none for the company, a shop or a client otherwise.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $type = AttachmentType::findOrFail($this->integer('attachment_type_id'));
                $owners = app(AttachmentAlerts::class)->owners()[$type->applies_to];

                $valid = $type->applies_to === 'company'
                    ? ! $this->filled('owner_id')
                    : array_key_exists($this->integer('owner_id'), $owners);

                if (! $valid) {
                    $validator->errors()->add('owner_id', __('validation.exists', ['attribute' => __('validation.attributes.owner_id')]));

                    return;
                }

                if (! $this->boolean('confirm_duplicate') && $this->alreadyKept($type)) {
                    $validator->errors()->add('duplicate', __('attachments.form.duplicate'));
                }
            },
        ];
    }
}
