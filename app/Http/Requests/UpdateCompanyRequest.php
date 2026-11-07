<?php

namespace App\Http\Requests;

use App\Support\BusinessSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    /**
     * Every field of the company page is optional text; the e-mail and the website must look like one.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return collect(app(BusinessSettings::class)->company())
            ->map(fn (array $definition, string $key): array => match ($key) {
                'company_email' => ['nullable', 'email:rfc', 'max:255'],
                'company_website' => ['nullable', 'url:http,https', 'max:255'],
                'company_footer_note' => ['nullable', 'string', 'max:500'],
                default => ['nullable', 'string', 'max:255'],
            })
            ->all();
    }
}
