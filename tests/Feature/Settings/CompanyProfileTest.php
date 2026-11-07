<?php

use App\Models\User;
use App\Support\BusinessSettings;
use App\Support\CompanyProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function pngFile(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'logo').'.png';
    $image = imagecreatetruecolor(40, 20);
    imagepng($image, $path);

    return new UploadedFile($path, 'logo.png', 'image/png', null, true);
}

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
});

test('the owner opens the company page with the stored details', function () {
    app(BusinessSettings::class)->set('company_name', 'Zebe SARL');

    $this->actingAs($this->owner)->get(route('company.edit'))->assertInertia(fn (Assert $page) => $page
        ->component('company/edit')
        ->where('values.company_name', 'Zebe SARL')
        ->where('hasLogo', false));
});

test('only the owner reaches the company page', function () {
    $this->actingAs(User::factory()->create())->get(route('company.edit'))->assertForbidden();
    $this->patch(route('company.update'), ['company_name' => 'X'])->assertForbidden();
    $this->post(route('company.logo.store'), ['logo' => pngFile()])->assertForbidden();
});

test('the company details are saved and checked', function () {
    $this->actingAs($this->owner)->patch(route('company.update'), [
        'company_name' => 'Zebe SARL',
        'company_legal_form' => 'SARL',
        'company_capital' => '1 000 000 FCFA',
        'company_email' => 'contact@zebe.ci',
        'company_website' => 'https://zebe.ci',
        'company_footer_note' => 'Paiement à réception.',
    ])->assertSessionHasNoErrors()->assertRedirect(route('company.edit'));

    $settings = app(BusinessSettings::class);

    expect($settings->get('company_name'))->toBe('Zebe SARL')
        ->and($settings->get('company_email'))->toBe('contact@zebe.ci')
        ->and($settings->get('company_footer_note'))->toBe('Paiement à réception.');

    $this->patch(route('company.update'), ['company_email' => 'not-an-email', 'company_website' => 'zebe'])
        ->assertSessionHasErrors(['company_email', 'company_website']);
});

test('the parameters page no longer holds the company details', function () {
    $this->actingAs($this->owner)->get(route('parameters.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('values', fn ($values) => ! collect($values)->has('company_name') && ! collect($values)->has('company_logo')));
});

test('a logo is kept, replaced and removed, and nothing else is accepted', function () {
    $this->actingAs($this->owner);

    $this->post(route('company.logo.store'), ['logo' => pngFile()])->assertSessionHasNoErrors();
    $first = app(BusinessSettings::class)->get('company_logo');

    expect($first)->toStartWith('company/');
    Storage::disk('local')->assertExists($first);
    $this->get(route('company.logo'))->assertOk();

    $this->post(route('company.logo.store'), ['logo' => pngFile()])->assertSessionHasNoErrors();

    Storage::disk('local')->assertMissing($first);

    $this->post(route('company.logo.store'), ['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')])->assertSessionHasErrors('logo');

    $this->delete(route('company.logo.destroy'))->assertRedirect(route('company.edit'));

    expect(app(BusinessSettings::class)->get('company_logo'))->toBeNull();
    $this->get(route('company.logo'))->assertNotFound();
});

test('the documents print the same header and footer with the company details and the logo', function () {
    $settings = app(BusinessSettings::class);
    $settings->set('company_name', 'Zebe SARL');
    $settings->set('company_legal_form', 'SARL');
    $settings->set('company_email', 'contact@zebe.ci');
    $settings->set('company_footer_note', 'Paiement à réception.');
    $this->actingAs($this->owner)->post(route('company.logo.store'), ['logo' => pngFile()]);

    $company = app(CompanyProfile::class)->forDocuments('fr');

    expect($company['logo'])->toStartWith('data:image/png;base64,')
        ->and($company['legal_form'])->toBe('SARL');

    $head = view('documents.letterhead', ['company' => $company])->render();
    $foot = view('documents.company_footer', ['company' => $company, 'extra' => 'FAC-1'])->render();

    expect($head)->toContain('Zebe SARL')->toContain('SARL')->toContain('contact@zebe.ci')->toContain('data:image/png;base64,')
        ->and($foot)->toContain('Zebe SARL')->toContain('Paiement à réception.')->toContain('FAC-1');
});
