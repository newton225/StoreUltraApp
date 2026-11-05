<?php

use App\Models\AttachmentType;
use App\Models\User;
use Database\Seeders\InitialAttachmentTypesSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function attachmentTypePayload(array $overrides = []): array
{
    return ['code' => 'permit', 'label_fr' => 'Autorisation', 'label_en' => 'Permit', 'applies_to' => 'company', ...$overrides];
}

test('only the owner manages the kinds of document', function () {
    $type = AttachmentType::factory()->create();

    foreach ([User::factory()->create(), User::factory()->agent()->create()] as $user) {
        $this->actingAs($user);

        $this->get(route('attachment-types.index'))->assertForbidden();
        $this->post(route('attachment-types.store'), attachmentTypePayload())->assertForbidden();
        $this->patch(route('attachment-types.update', $type), attachmentTypePayload())->assertForbidden();
    }

    expect(AttachmentType::count())->toBe(1);
});

test('the owner lists, creates and updates the kinds', function () {
    AttachmentType::factory()->count(2)->create();
    $this->actingAs(User::factory()->owner()->create());

    $this->get(route('attachment-types.index'))
        ->assertInertia(fn (Assert $page) => $page->component('attachment-types/index')->has('types', 2));

    $this->post(route('attachment-types.store'), attachmentTypePayload(['required' => true, 'on_documents' => true]))
        ->assertSessionHasNoErrors();

    $type = AttachmentType::where('code', 'permit')->firstOrFail();

    expect($type->required)->toBeTrue();
    expect($type->on_documents)->toBeTrue();
    expect($type->has_expiry)->toBeFalse();

    $this->patch(route('attachment-types.update', $type), attachmentTypePayload([
        'code' => 'changed',
        'applies_to' => 'client',
        'label_fr' => 'Licence',
        'required' => false,
        'active' => false,
    ]))->assertSessionHasNoErrors();

    $type->refresh();

    expect($type->label_fr)->toBe('Licence');
    expect($type->required)->toBeFalse();
    expect($type->active)->toBeFalse();
    expect($type->code)->toBe('permit');
    expect($type->applies_to)->toBe('company');
});

test('a kind needs a unique code and a known category', function () {
    AttachmentType::factory()->create(['code' => 'permit']);
    $this->actingAs(User::factory()->owner()->create());

    $this->post(route('attachment-types.store'), attachmentTypePayload())->assertSessionHasErrors('code');
    $this->post(route('attachment-types.store'), attachmentTypePayload(['code' => 'Bad Code']))->assertSessionHasErrors('code');
    $this->post(route('attachment-types.store'), attachmentTypePayload(['code' => 'other', 'applies_to' => 'planet']))->assertSessionHasErrors('applies_to');
});

test('the initial kinds are seeded once', function () {
    $this->seed(InitialAttachmentTypesSeeder::class);
    $this->seed(InitialAttachmentTypesSeeder::class);

    expect(AttachmentType::count())->toBe(7);
    expect(AttachmentType::where('required', true)->where('applies_to', 'company')->count())->toBe(2);
});

test('printing on our documents is only kept for the types of the company', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('attachment-types.store'), ['label_fr' => 'Registre boutique', 'label_en' => 'Shop register', 'applies_to' => 'shop', 'on_documents' => 1])->assertSessionHasNoErrors();
    $this->post(route('attachment-types.store'), ['label_fr' => 'Registre entreprise', 'label_en' => 'Company register', 'applies_to' => 'company', 'on_documents' => 1])->assertSessionHasNoErrors();

    expect(AttachmentType::query()->where('label_fr', 'Registre boutique')->value('on_documents'))->toBeFalse()
        ->and(AttachmentType::query()->where('label_fr', 'Registre entreprise')->value('on_documents'))->toBeTrue();
});
