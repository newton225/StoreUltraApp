<?php

namespace App\Rules;

use App\Support\UploadedFiles;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * A file is kept only for what its bytes say it is: a PDF without visible active content, or a JPEG or
 * PNG image that can be read and is not huge. A file that is called an image but is a script, an SVG or
 * a page is refused whatever its name.
 */
class SafeUpload implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $files = app(UploadedFiles::class);

        if (! $value instanceof UploadedFile || ! $value->isValid() || ! isset(UploadedFiles::TYPES[(string) $value->getMimeType()])) {
            $fail(__('validation.upload.type'));

            return;
        }

        $mime = (string) $value->getMimeType();

        if ($mime === 'application/pdf') {
            if ($files->pdfHasActiveContent((string) file_get_contents($value->getRealPath()))) {
                $fail(__('validation.upload.pdf_active'));
            }

            return;
        }

        $info = @getimagesize($value->getRealPath());

        if ($info === false) {
            $fail(__('validation.upload.invalid_image'));
        } elseif ($info[0] * $info[1] > UploadedFiles::MAX_PIXELS) {
            $fail(__('validation.upload.image_too_large'));
        }
    }
}
