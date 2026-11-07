<?php

namespace App\Http\Requests;

use App\Rules\SafeUpload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyLogoRequest extends FormRequest
{
    /**
     * A logo is a PNG or a JPEG image (a PDF has no place in a header), checked on its bytes like every upload.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'logo' => ['required', 'file', 'mimetypes:image/png,image/jpeg', 'max:2048', new SafeUpload],
        ];
    }
}
