<?php

use App\Actions\Attachments\DeleteAttachment;
use App\Actions\Attachments\RecordAttachment;
use App\Models\Attachment;
use App\Models\AttachmentType;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
    $this->type = AttachmentType::factory()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function attachmentPayload(AttachmentType $type, array $overrides = []): array
{
    return ['attachment_type_id' => $type->id, 'number' => 'CI-ABJ-2024-B-12345', ...$overrides];
}

test('only the owner reaches the documents', function () {
    $attachment = Attachment::factory()->create(['attachment_type_id' => $this->type->id]);

    foreach ([User::factory()->create(), User::factory()->agent()->create()] as $user) {
        $this->actingAs($user);

        $this->get(route('attachments.index'))->assertForbidden();
        $this->post(route('attachments.store'), attachmentPayload($this->type))->assertForbidden();
        $this->delete(route('attachments.destroy', $attachment))->assertForbidden();
        $this->get(route('attachments.file', $attachment))->assertForbidden();
    }

    expect(Attachment::count())->toBe(1);
});

test('the number alone is enough to keep a company document', function () {
    $this->actingAs($this->owner)
        ->post(route('attachments.store'), attachmentPayload($this->type))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('attachments.index', ['kind' => 'company']));

    $attachment = Attachment::firstOrFail();

    expect($attachment->number)->toBe('CI-ABJ-2024-B-12345');
    expect($attachment->owner_id)->toBeNull();
    expect($attachment->file_path)->toBeNull();
    expect($attachment->created_by)->toBe($this->owner->id);
    expect(AuditLog::where('action', 'attachment.recorded')->count())->toBe(1);
});

test('a document keeps its dates and its private file', function () {
    $this->actingAs($this->owner)
        ->post(route('attachments.store'), attachmentPayload($this->type, [
            'issued_on' => '2024-01-10',
            'expires_on' => '2027-01-10',
            'file' => UploadedFile::fake()->create('rccm.pdf', 200, 'application/pdf'),
        ]))
        ->assertSessionHasNoErrors();

    $attachment = Attachment::firstOrFail();

    expect($attachment->expires_on?->toDateString())->toBe('2027-01-10');
    Storage::disk('local')->assertExists((string) $attachment->file_path);

    $this->get(route('attachments.file', $attachment))->assertOk();
});

test('a document without a file has nothing to open', function () {
    $attachment = Attachment::factory()->create(['attachment_type_id' => $this->type->id]);

    $this->actingAs($this->owner)->get(route('attachments.file', $attachment))->assertNotFound();
});

test('the dates and the file are checked', function () {
    $this->actingAs($this->owner);

    $this->post(route('attachments.store'), attachmentPayload($this->type, ['issued_on' => '2024-05-01', 'expires_on' => '2024-04-01']))
        ->assertSessionHasErrors('expires_on');
    $this->post(route('attachments.store'), attachmentPayload($this->type, ['number' => '']))->assertSessionHasErrors('number');
    $this->post(route('attachments.store'), attachmentPayload($this->type, ['file' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')]))
        ->assertSessionHasErrors('file');
    $this->post(route('attachments.store'), attachmentPayload($this->type, ['file' => UploadedFile::fake()->create('big.pdf', 5000, 'application/pdf')]))
        ->assertSessionHasErrors('file');

    expect(Attachment::count())->toBe(0);
});

test('an inactive or unknown kind is refused', function () {
    $this->actingAs($this->owner);
    $inactive = AttachmentType::factory()->create(['active' => false]);

    $this->post(route('attachments.store'), attachmentPayload($inactive))->assertSessionHasErrors('attachment_type_id');
    $this->post(route('attachments.store'), ['attachment_type_id' => 999, 'number' => '1'])->assertSessionHasErrors('attachment_type_id');
});

test('a shop or client document needs an owner of the right kind', function () {
    $shopType = AttachmentType::factory()->forKind('shop')->create();
    $clientType = AttachmentType::factory()->forKind('client')->create();
    $shop = Shop::factory()->create();
    $client = Client::factory()->create();
    $this->actingAs($this->owner);

    $this->post(route('attachments.store'), attachmentPayload($shopType))->assertSessionHasErrors('owner_id');
    $this->post(route('attachments.store'), attachmentPayload($shopType, ['owner_id' => $client->id + 100]))->assertSessionHasErrors('owner_id');
    $this->post(route('attachments.store'), attachmentPayload($this->type, ['owner_id' => $shop->id]))->assertSessionHasErrors('owner_id');

    $this->post(route('attachments.store'), attachmentPayload($shopType, ['owner_id' => $shop->id]))->assertSessionHasNoErrors();
    $this->post(route('attachments.store'), attachmentPayload($clientType, ['owner_id' => $client->id]))->assertSessionHasNoErrors();

    expect(Attachment::count())->toBe(2);
});

test('the index shows the documents of the chosen category with their owner', function () {
    $shopType = AttachmentType::factory()->forKind('shop')->create();
    $shop = Shop::factory()->create(['name' => 'Quincaillerie Centrale']);
    Attachment::factory()->create(['attachment_type_id' => $this->type->id]);
    Attachment::factory()->create(['attachment_type_id' => $shopType->id, 'owner_id' => $shop->id]);

    $this->actingAs($this->owner)
        ->get(route('attachments.index', ['kind' => 'shop']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('attachments/index')
            ->where('kind', 'shop')
            ->has('attachments.data', 1)
            ->where('attachments.data.0.owner', 'Quincaillerie Centrale')
            ->has('types', 1));

    $this->get(route('attachments.index', ['kind' => 'nonsense']))
        ->assertInertia(fn (Assert $page) => $page->where('kind', 'company')->has('attachments.data', 1));
});

test('removing a document deletes its file and keeps a trace', function () {
    $this->actingAs($this->owner)->post(route('attachments.store'), attachmentPayload($this->type, [
        'file' => UploadedFile::fake()->create('rccm.pdf', 100, 'application/pdf'),
    ]));
    $attachment = Attachment::firstOrFail();
    $path = (string) $attachment->file_path;

    $this->delete(route('attachments.destroy', $attachment))->assertRedirect();

    expect(Attachment::count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
    expect(AuditLog::where('action', 'attachment.deleted')->count())->toBe(1);
});

test('a document and its trace are written together, with its file', function () {
    $attachment = app(RecordAttachment::class)->handle(
        ['attachment_type_id' => $this->type->id, 'number' => 'RC-1'],
        UploadedFile::fake()->create('rc.pdf', 10, 'application/pdf'),
        $this->owner,
    );

    expect(Attachment::count())->toBe(1);
    expect(AuditLog::where('action', 'attachment.recorded')->count())->toBe(1);
    Storage::disk('local')->assertExists($attachment->file_path);
});

test('when the trace cannot be written, no document and no file are left', function () {
    AuditLog::creating(function (): void {
        throw new RuntimeException('the journal is unavailable');
    });

    expect(fn () => app(RecordAttachment::class)->handle(
        ['attachment_type_id' => $this->type->id, 'number' => 'RC-1'],
        UploadedFile::fake()->create('rc.pdf', 10, 'application/pdf'),
        $this->owner,
    ))->toThrow(RuntimeException::class);

    expect(Attachment::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('a deleted document loses its row, its file and leaves a trace', function () {
    $attachment = app(RecordAttachment::class)->handle(
        ['attachment_type_id' => $this->type->id, 'number' => 'RC-1'],
        UploadedFile::fake()->create('rc.pdf', 10, 'application/pdf'),
        $this->owner,
    );
    $path = $attachment->file_path;

    app(DeleteAttachment::class)->handle($attachment, $this->owner);

    expect(Attachment::count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
    expect(AuditLog::where('action', 'attachment.deleted')->count())->toBe(1);
});

test('a document that cannot be removed keeps its file and its trace stays out', function () {
    $attachment = app(RecordAttachment::class)->handle(
        ['attachment_type_id' => $this->type->id, 'number' => 'RC-1'],
        UploadedFile::fake()->create('rc.pdf', 10, 'application/pdf'),
        $this->owner,
    );
    Attachment::deleting(function (): void {
        throw new RuntimeException('the database refuses');
    });

    expect(fn () => app(DeleteAttachment::class)->handle($attachment, $this->owner))->toThrow(RuntimeException::class);

    expect(Attachment::count())->toBe(1);
    Storage::disk('local')->assertExists($attachment->file_path);
    expect(AuditLog::where('action', 'attachment.deleted')->count())->toBe(0);
});

test('an image is shown as it is and a download is offered on request, a pdf is always a download', function () {
    Storage::fake('local');
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    Storage::disk('local')->put('attachments/photo.png', $png);
    Storage::disk('local')->put('attachments/paper.pdf', '%PDF-1.4 test');

    $image = Attachment::factory()->create(['attachment_type_id' => $this->type->id, 'file_path' => 'attachments/photo.png']);
    $pdf = Attachment::factory()->create(['attachment_type_id' => $this->type->id, 'file_path' => 'attachments/paper.pdf']);

    $this->actingAs($this->owner);

    expect((string) $this->get(route('attachments.file', $image))->assertOk()->headers->get('content-disposition'))
        ->not->toContain('attachment');
    expect((string) $this->get(route('attachments.file', ['attachment' => $image, 'download' => 1]))->assertOk()->headers->get('content-disposition'))
        ->toContain('attachment');
    expect((string) $this->get(route('attachments.file', $pdf))->assertOk()->headers->get('content-disposition'))
        ->toContain('attachment');
});

test('a number already kept for the same owner and type is flagged once, then saved on confirmation', function () {
    $shop = Shop::factory()->create();
    $type = AttachmentType::factory()->create(['applies_to' => 'shop']);
    $payload = ['attachment_type_id' => $type->id, 'owner_id' => $shop->id, 'number' => 'CC0003'];

    $this->actingAs($this->owner)->post(route('attachments.store'), $payload)->assertSessionHasNoErrors();

    $this->post(route('attachments.store'), $payload)->assertSessionHasErrors('duplicate');
    expect(Attachment::count())->toBe(1);

    $this->post(route('attachments.store'), [...$payload, 'confirm_duplicate' => 1])->assertSessionHasNoErrors();
    expect(Attachment::count())->toBe(2);

    $other = Shop::factory()->create();
    $this->post(route('attachments.store'), [...$payload, 'owner_id' => $other->id])->assertSessionHasNoErrors();
    expect(Attachment::count())->toBe(3);
});
