<?php

use App\Support\BusinessSettings;
use App\Support\HealthReport;
use App\Support\LegalTexts;
use App\Support\ProductionCheck;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Everything the texts need, filled in.
 */
function completeLegalIdentity(): void
{
    $settings = app(BusinessSettings::class);
    $settings->set('company_name', 'Zebe Kits SARL');
    $settings->set('company_address', 'Cocody, Abidjan');
    $settings->set('company_email', 'contact@zebe.ci');
    $settings->set('company_signatory', 'A. Kone');
    $settings->set('company_signatory_title', 'Gérant');

    config([
        'legal.host_name' => 'Hébergeur Exemple',
        'legal.host_address' => '1 rue du Serveur, Paris',
        'legal.host_country' => 'France',
        'legal.mail_provider' => 'Courrier Exemple',
        'legal.country' => 'Côte d\'Ivoire',
        'legal.data_protection_law' => 'Loi n° 2013-450',
        'legal.data_protection_authority' => 'ARTCI',
    ]);
}

test('the three legal texts are public, in both languages, and unknown ones do not exist', function () {
    foreach (LegalTexts::DOCUMENTS as $document) {
        $this->get(route('legal.show', $document))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('legal/show')->where('document.key', $document)->has('document.sections'));
    }

    $this->withSession(['locale' => 'en'])->get(route('legal.show', 'privacy'))->assertOk();
    $this->get('/legal/secret')->assertNotFound();
});

test('a value that is not set is printed as a blank to complete, and a set one is printed as is', function () {
    $blank = app(LegalTexts::class)->document('notice', 'fr');
    expect(json_encode($blank, JSON_UNESCAPED_UNICODE))->toContain('[à compléter]')->not->toContain('Zebe Kits SARL');

    completeLegalIdentity();

    $filled = json_encode(app(LegalTexts::class)->document('notice', 'fr'), JSON_UNESCAPED_UNICODE);

    expect($filled)->toContain('Zebe Kits SARL')->toContain('Hébergeur Exemple')->toContain('A. Kone, Gérant')->not->toContain('[à compléter]');
});

test('the durations announced are the ones the code enforces', function () {
    completeLegalIdentity();
    config([
        'security.access_log_retention_days' => 111,
        'session.lifetime' => 77,
        'backup.retention.max_days' => 222,
        'security.audit_archive_retention_months' => 33,
    ]);

    $privacy = json_encode(app(LegalTexts::class)->document('privacy', 'fr'), JSON_UNESCAPED_UNICODE);

    expect($privacy)->toContain('111 jours')->toContain('77 minutes')->toContain('222 jours')->toContain('33 mois');

    config(['security.access_log_retention_days' => 45]);

    expect(json_encode(app(LegalTexts::class)->document('privacy', 'fr'), JSON_UNESCAPED_UNICODE))->toContain('45 jours')->not->toContain('111 jours');
});

test('the legal texts are the same in both languages and carry the version', function () {
    completeLegalIdentity();

    foreach (LegalTexts::DOCUMENTS as $document) {
        $fr = app(LegalTexts::class)->document($document, 'fr');
        $en = app(LegalTexts::class)->document($document, 'en');

        expect(count($en['sections']))->toBe(count($fr['sections']))
            ->and($fr['version'])->toBe(config('legal.version'))
            ->and($en['title'])->not->toBe($fr['title']);
    }
});

test('the legal texts are not sent with every page', function () {
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->missing('document')->missing('legal'));
});

test('a production server needs the legal settings and a legal review', function () {
    // The safe settings switch the connection to PostgreSQL for the check; this test goes on using its own database.
    $connection = config('database.default');
    useSafeProductionSettings();
    config(['database.default' => $connection, 'legal.host_name' => '', 'legal.reviewed_version' => null]);

    $errors = implode(' ', app(ProductionCheck::class)->run()['errors']);

    expect($errors)->toContain('LEGAL_HOST_NAME')->toContain('not confirmed by a legal review');

    completeLegalIdentity();
    config(['legal.reviewed_version' => config('legal.version')]);

    // (the SQLite error left is about the database of this test, not the legal texts)
    expect(implode(' ', app(ProductionCheck::class)->run()['errors']))->not->toContain('LEGAL_')->not->toContain('legal review');
});

test('the identity blanks of the company page are reported by the command check, and the health screen', function () {
    completeLegalIdentity();
    app(BusinessSettings::class)->set('company_email', null);
    config(['legal.privacy_email' => '']);

    expect(app(ProductionCheck::class)->legalIdentityBlanks()[0])->toContain('email');

    $legal = collect(app(HealthReport::class)->checks())->firstWhere('key', 'legal');

    expect($legal)->toMatchArray(['status' => 'warning', 'detail' => 'blanks']);
});
