<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string|Closure>
     */
    public function rules(): array
    {
        $type = DocumentType::tryFrom((string) $this->input('type'));
        $isStatement = $type?->hasNoSubject() ?? false;

        return [
            'type' => ['required', Rule::enum(DocumentType::class)],
            'locale' => ['required', 'string', Rule::in(config('app.supported_locales'))],
            'subject_id' => [
                Rule::requiredIf(! $isStatement),
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($type): void {
                    $model = $type?->subjectModel();

                    if ($model !== null && ! $model::query()->whereKey($value)->exists()) {
                        $fail(__('validation.exists', ['attribute' => __('validation.attributes.subject_id')]));
                    }
                },
            ],
            'period_start' => [Rule::requiredIf($isStatement), 'nullable', 'date'],
            'period_end' => [Rule::requiredIf($isStatement), 'nullable', 'date', 'after_or_equal:period_start'],
            'fne_number' => ['nullable', 'string', 'max:60'],
        ];
    }
}
