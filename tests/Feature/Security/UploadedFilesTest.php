<?php

use App\Actions\Attachments\RecordAttachment;
use App\Http\Requests\StoreDeliveryRequest;
use App\Models\Attachment;
use App\Models\AttachmentType;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\User;
use App\Support\UploadedFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
    $this->type = AttachmentType::factory()->create();
});

function jpegBytes(): string
{
    ob_start();
    imagejpeg(imagecreatetruecolor(20, 10));

    return (string) ob_get_clean();
}

function pngBytes(): string
{
    ob_start();
    imagepng(imagecreatetruecolor(20, 10));

    return (string) ob_get_clean();
}

/**
 * A JPEG with a comment segment, the way a camera or an editor leaves its metadata.
 */
function jpegWithComment(string $comment): string
{
    $jpeg = jpegBytes();

    return substr($jpeg, 0, 2)."\xFF\xFE".pack('n', strlen($comment) + 2).$comment.substr($jpeg, 2);
}

/**
 * A PNG with a text chunk before its end.
 */
function pngWithText(string $text): string
{
    $png = pngBytes();
    $data = 'Comment'."\0".$text;
    $chunk = pack('N', strlen($data)).'tEXt'.$data.pack('N', crc32('tEXt'.$data));

    return substr($png, 0, -12).$chunk.substr($png, -12);
}

/**
 * The header of a PNG that says it is this big, with nothing behind it.
 */
function pngClaiming(int $width, int $height): string
{
    $data = pack('N', $width).pack('N', $height)."\x08\x02\x00\x00\x00";
    $header = pack('N', strlen($data)).'IHDR'.$data.pack('N', crc32('IHDR'.$data));

    return "\x89PNG\r\n\x1a\n".$header.pack('N', 0).'IEND'.pack('N', crc32('IEND'));
}

/**
 * A file as the browser sends it: its type is found from its bytes, whatever its name says (the fake
 * of the framework takes it from the name).
 */
function realUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

function cleanPdf(): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
}

function sendDocument(mixed $test, string $name, string $content): TestResponse
{
    return $test->actingAs($test->owner)->post(route('attachments.store'), [
        'attachment_type_id' => $test->type->id,
        'number' => 'RC-1',
        'file' => realUpload($name, $content),
    ]);
}

function storedContent(): string
{
    return Storage::disk('local')->get(Attachment::sole()->file_path);
}

test('the metadata an image carries is not kept', function () {
    sendDocument($this, 'photo.jpg', jpegWithComment('SECRET-GPS-4.0N-3.9W'))->assertSessionHasNoErrors();

    $stored = storedContent();

    expect($stored)->not->toContain('SECRET-GPS');
    expect(getimagesizefromstring($stored)['mime'])->toBe('image/jpeg');
});

test('a text chunk hidden in a png is not kept', function () {
    sendDocument($this, 'scan.png', pngWithText('SECRET-NOTE'))->assertSessionHasNoErrors();

    $stored = storedContent();

    expect($stored)->not->toContain('SECRET-NOTE');
    expect(getimagesizefromstring($stored)['mime'])->toBe('image/png');
});

test('what follows the end of an image, such as a page or a script, is not kept', function () {
    sendDocument($this, 'scan.png', pngBytes().'<html><script>alert(1)</script></html>')->assertSessionHasNoErrors();

    expect(storedContent())->not->toContain('<script');
});

test('a transparent png stays transparent', function () {
    $image = imagecreatetruecolor(2, 2);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagesetpixel($image, 0, 0, imagecolorallocatealpha($image, 10, 20, 30, 127));
    ob_start();
    imagepng($image);

    $clean = app(UploadedFiles::class)->cleanImage((string) ob_get_clean(), 'image/png');

    expect($clean)->not->toBeNull();
    expect((imagecolorat(imagecreatefromstring($clean), 0, 0) >> 24) & 0x7F)->toBe(127);
});

test('the stored name is random and never the one sent', function () {
    sendDocument($this, 'registre-de-commerce.png', pngBytes());

    expect(Attachment::sole()->file_path)->toMatch('#^attachments/[A-Za-z0-9]{40}\.png$#');
});

test('the type is the one in the bytes, not the one in the name', function (string $name, string $content) {
    sendDocument($this, $name, $content)->assertSessionHasErrors('file');

    expect(Attachment::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'an svg called png' => ['logo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    'a page called pdf' => ['facture.pdf', '<html><body><script>alert(1)</script></body></html>'],
    'a php script called jpg' => ['photo.jpg', '<?php echo shell_exec($_GET["c"]);'],
    'a text called png' => ['scan.png', 'just some text'],
]);

test('an image that cannot be read is refused', function () {
    sendDocument($this, 'scan.png', "\x89PNG\r\n\x1a\n".str_repeat('garbage', 50))->assertSessionHasErrors('file');
});

test('an image that is far too big to decode safely is refused before it is decoded', function () {
    sendDocument($this, 'huge.png', pngClaiming(10000, 10000))->assertSessionHasErrors('file');

    expect(Attachment::count())->toBe(0);
});

test('a clean pdf is kept as it is', function () {
    sendDocument($this, 'rccm.pdf', cleanPdf())->assertSessionHasNoErrors();

    expect(Attachment::sole()->file_path)->toMatch('#^attachments/[A-Za-z0-9]{40}\.pdf$#');
    expect(storedContent())->toBe(cleanPdf());
});

test('a pdf with a script or something that launches is refused, even when its names are disguised', function (string $active) {
    sendDocument($this, 'rccm.pdf', cleanPdf().$active)->assertSessionHasErrors('file');

    expect(Attachment::count())->toBe(0);
})->with([
    'javascript' => ['<< /S /JavaScript /JS (app.alert(1)) >>'],
    'js alone' => ['<< /JS (app.alert(1)) >>'],
    'disguised with hex escapes' => ['<< /S /J#61vaScr#69pt >>'],
    'launch' => ['<< /S /Launch /F (cmd.exe) >>'],
    'embedded file' => ['<< /Type /EmbeddedFile >>'],
]);

test('a pdf is offered as a download and an image as it is, and each opening is in the journal', function () {
    $pdf = app(RecordAttachment::class)->handle(
        ['attachment_type_id' => $this->type->id, 'number' => 'A'],
        realUpload('a.pdf', cleanPdf()),
        $this->owner,
    );
    $image = app(RecordAttachment::class)->handle(
        ['attachment_type_id' => $this->type->id, 'number' => 'B'],
        realUpload('b.png', pngBytes()),
        $this->owner,
    );
    $this->actingAs($this->owner);

    $download = $this->get(route('attachments.file', $pdf));
    $inline = $this->get(route('attachments.file', $image));

    expect($download->headers->get('Content-Disposition'))->toContain('attachment');
    expect($inline->headers->get('Content-Disposition'))->not->toContain('attachment');
    expect(AuditLog::where('action', 'attachment.opened')->count())->toBe(2);
});

test('the proof of a cost and a signature are in the journal each time they are opened', function () {
    Storage::disk('local')->put('expenses/proof.png', pngBytes());
    Storage::disk('local')->put('signatures/delivery-1.png', pngBytes());
    $expense = Expense::factory()->create(['proof_path' => 'expenses/proof.png']);
    $delivery = Delivery::factory()->create(['signature_path' => 'signatures/delivery-1.png']);
    $this->actingAs($this->owner);

    $this->get(route('expenses.proof', $expense))->assertOk();
    $this->get(route('expenses.proof', $expense))->assertOk();
    $this->get(route('deliveries.signature', $delivery))->assertOk();

    expect(AuditLog::where('action', 'expense.proof_opened')->count())->toBe(2);
    expect(AuditLog::where('action', 'delivery.signature_opened')->count())->toBe(1);
});

test('a signature is written again from its pixels', function () {
    $signature = StoreDeliveryRequest::decodeSignature('data:image/png;base64,'.base64_encode(pngWithText('SECRET-NOTE')));

    expect($signature)->not->toBeNull()->not->toContain('SECRET-NOTE');
    expect(getimagesizefromstring($signature)['mime'])->toBe('image/png');
    expect(StoreDeliveryRequest::decodeSignature('data:image/png;base64,'.base64_encode(pngClaiming(10000, 10000))))->toBeNull();
});

test('an image is not written again as a type it is not', function () {
    expect(app(UploadedFiles::class)->cleanImage(jpegBytes(), 'image/png'))->toBeNull();
    expect(app(UploadedFiles::class)->cleanImage('not an image', 'image/png'))->toBeNull();
});

test('a user may send twenty files a minute, not more', function () {
    $this->actingAs($this->owner);

    foreach (range(1, 20) as $attempt) {
        $this->post(route('attachments.store'), [])->assertSessionHasErrors();
    }

    $this->post(route('attachments.store'), [])->assertStatus(429);
});
