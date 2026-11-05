<?php

use App\Documents\DocumentIssuer;
use App\Enums\DocumentType;
use App\Models\Attachment;
use App\Models\AttachmentType;
use App\Models\Client;
use App\Models\Shop;
use App\Support\AttachmentAlerts;
use App\Support\BusinessSettings;

beforeEach(function () {
    app(BusinessSettings::class)->set('company_name', 'Zebe Kits SARL');
});

test('a required document that is not kept is reported as missing', function () {
    AttachmentType::factory()->required()->create(['label_fr' => 'RCCM']);
    AttachmentType::factory()->create();

    $alerts = app(AttachmentAlerts::class)->all();

    expect($alerts['missing'])->toHaveCount(1);
    expect($alerts['missing'][0])->toMatchArray(['type' => 'RCCM', 'kind' => 'company', 'owner' => 'Zebe Kits SARL']);
});

test('a kept document clears the missing alert, even without a file', function () {
    $type = AttachmentType::factory()->required()->create();
    Attachment::factory()->create(['attachment_type_id' => $type->id]);

    expect(app(AttachmentAlerts::class)->all()['missing'])->toBe([]);
});

test('a required kind for shops is missing once per active shop without it', function () {
    $type = AttachmentType::factory()->required()->forKind('shop')->create();
    $covered = Shop::factory()->create();
    Shop::factory()->create(['name' => 'Sans document']);
    Shop::factory()->create(['active' => false]);
    Attachment::factory()->create(['attachment_type_id' => $type->id, 'owner_id' => $covered->id]);

    $missing = app(AttachmentAlerts::class)->all()['missing'];

    expect($missing)->toHaveCount(1);
    expect($missing[0]['owner'])->toBe('Sans document');
});

test('an inactive required kind raises nothing', function () {
    AttachmentType::factory()->required()->create(['active' => false]);

    expect(app(AttachmentAlerts::class)->all()['missing'])->toBe([]);
});

test('clients that ended are not asked for documents', function () {
    AttachmentType::factory()->required()->forKind('client')->create();
    Client::factory()->create(['status' => 'ended']);
    $active = Client::factory()->create();

    $missing = app(AttachmentAlerts::class)->all()['missing'];

    expect($missing)->toHaveCount(1);
    expect($missing[0]['owner'])->toBe($active->name);
});

test('documents expiring within the delay or already expired are reported', function () {
    $this->travelTo(now()->setDate(2026, 6, 1));
    $type = AttachmentType::factory()->create(['has_expiry' => true]);
    $soon = Attachment::factory()->create(['attachment_type_id' => $type->id, 'expires_on' => '2026-06-20']);
    $expired = Attachment::factory()->create(['attachment_type_id' => AttachmentType::factory()->create()->id, 'expires_on' => '2026-05-30']);
    Attachment::factory()->create(['attachment_type_id' => AttachmentType::factory()->create()->id, 'expires_on' => '2026-09-01']);
    Attachment::factory()->create(['attachment_type_id' => AttachmentType::factory()->create()->id, 'expires_on' => null]);

    $alerts = app(AttachmentAlerts::class)->all();

    expect($alerts['expiring'])->toHaveCount(1);
    expect($alerts['expiring'][0])->toMatchArray(['id' => $soon->id, 'days' => 19]);
    expect($alerts['expired'])->toHaveCount(1);
    expect($alerts['expired'][0])->toMatchArray(['id' => $expired->id, 'days' => -2]);
});

test('the delay comes from the settings', function () {
    $this->travelTo(now()->setDate(2026, 6, 1));
    Attachment::factory()->create(['expires_on' => '2026-08-15']);

    expect(app(AttachmentAlerts::class)->all()['expiring'])->toBe([]);

    app(BusinessSettings::class)->set('attachment_alert_days', 90);

    expect(app(AttachmentAlerts::class)->all()['expiring'])->toHaveCount(1);
});

test('a renewal clears the alert of the old document', function () {
    $this->travelTo(now()->setDate(2026, 6, 1));
    $type = AttachmentType::factory()->create();
    Attachment::factory()->create(['attachment_type_id' => $type->id, 'expires_on' => '2026-05-01']);
    Attachment::factory()->create(['attachment_type_id' => $type->id, 'expires_on' => '2028-05-01']);

    $alerts = app(AttachmentAlerts::class)->all();

    expect($alerts['expired'])->toBe([]);
    expect($alerts['expiring'])->toBe([]);
});

test('the numbers of the company marked for printing appear on the documents', function () {
    $printed = AttachmentType::factory()->create(['label_fr' => 'RCCM', 'label_en' => 'Trade register', 'on_documents' => true]);
    $hidden = AttachmentType::factory()->create(['label_fr' => 'Secret', 'on_documents' => false]);
    $shop = AttachmentType::factory()->forKind('shop')->create(['label_fr' => 'Boutique RC', 'on_documents' => true]);
    Attachment::factory()->create(['attachment_type_id' => $printed->id, 'number' => 'CI-ABJ-2020-B-111']);
    Attachment::factory()->create(['attachment_type_id' => $printed->id, 'number' => 'CI-ABJ-2024-B-222']);
    Attachment::factory()->create(['attachment_type_id' => $hidden->id, 'number' => 'HIDDEN-1']);
    Attachment::factory()->create(['attachment_type_id' => $shop->id, 'owner_id' => 1, 'number' => 'SHOP-1']);

    $html = app(DocumentIssuer::class)->render(DocumentType::Proforma, realOrder('draft'), 'fr', 'NUM-TEST-0001');

    expect($html)
        ->toContain('RCCM : CI-ABJ-2024-B-222')
        ->not->toContain('CI-ABJ-2020-B-111')
        ->not->toContain('HIDDEN-1')
        ->not->toContain('SHOP-1');

    $english = app(DocumentIssuer::class)->render(DocumentType::Proforma, realOrder('draft'), 'en', 'NUM-TEST-0002');

    expect($english)->toContain('Trade register : CI-ABJ-2024-B-222');
});
