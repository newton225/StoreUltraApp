<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What the application does with a file somebody sends. The type is the one found in the bytes, never
 * the name or the extension given. An image is decoded and written again, which drops its metadata
 * (position, device), any script or text hidden in it, and whatever follows its end. A PDF is kept
 * as it is once it is free of visible active content, and is only ever offered as a download. The
 * stored name is random and never comes from the sender.
 */
class UploadedFiles
{
    /**
     * An image above this many pixels is refused: decoding it would need too much memory.
     */
    public const MAX_PIXELS = 16_000_000;

    /**
     * The types that are kept, with the extension each one is stored under.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /**
     * Keep a file on the private disk under a random name and give the path back.
     *
     * @throws RuntimeException when the file is not of a kept type, or an image cannot be read
     */
    public function store(UploadedFile $file, string $directory): string
    {
        $mime = (string) $file->getMimeType();
        $binary = (string) file_get_contents($file->getRealPath());

        if (! isset(self::TYPES[$mime])) {
            throw new RuntimeException('This type of file is not kept.');
        }

        $contents = $mime === 'application/pdf' ? $binary : $this->cleanImage($binary, $mime);

        if ($contents === null) {
            throw new RuntimeException('The image could not be read.');
        }

        $path = $directory.'/'.Str::random(40).'.'.self::TYPES[$mime];

        Storage::disk('local')->put($path, $contents);

        return $path;
    }

    /**
     * An image written again from its pixels, or null when it is unreadable, too big, or not of the
     * type it says.
     */
    public function cleanImage(string $binary, string $mime): ?string
    {
        $info = @getimagesizefromstring($binary);

        if ($info === false || $info[0] * $info[1] > self::MAX_PIXELS || $info['mime'] !== $mime) {
            return null;
        }

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            return null;
        }

        imagepalettetotruecolor($image);

        ob_start();

        if ($mime === 'image/jpeg') {
            imagejpeg($this->upright($image, $binary), null, 88);
        } else {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagepng($image);
        }

        $clean = (string) ob_get_clean();

        return $clean === '' ? null : $clean;
    }

    /**
     * Whether a PDF carries what runs or opens something: scripts, launched programs, embedded files,
     * forms that send data. Names may be written with #xx escapes to hide them, so those are decoded first.
     */
    public function pdfHasActiveContent(string $binary): bool
    {
        $decoded = preg_replace_callback('/#([0-9A-Fa-f]{2})/', fn (array $found): string => chr((int) hexdec($found[1])), $binary) ?? $binary;

        return preg_match('#/(JavaScript|JS|Launch|EmbeddedFile|RichMedia|XFA|SubmitForm|ImportData)\b#', $decoded) === 1;
    }

    /**
     * Give a stored file back to the browser: a PDF as a download, never opened in the page; an image as it is.
     */
    public function response(string $path): StreamedResponse
    {
        $disk = Storage::disk('local');

        return str_ends_with($path, '.pdf') ? $disk->download($path) : $disk->response($path);
    }

    /**
     * Give a stored file back as a download, whatever its type.
     */
    public function download(string $path): StreamedResponse
    {
        return Storage::disk('local')->download($path);
    }

    /**
     * A photo turned the way it was taken, since the orientation is lost with the metadata.
     */
    private function upright(GdImage $image, string $binary): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($binary));
        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }
}
