<?php

use App\Models\User;
use App\Support\BusinessSettings;
use Inertia\Testing\AssertableInertia as Assert;

function validSettingsPayload(array $overrides = []): array
{
    return [
        'kits_per_client' => 3,
        'advance_amount' => 200000,
        'advance_frequency' => 'weekly',
        'delivery_frequency' => 'weekly',
        'advance_required' => true,
        'delivery_signature_required' => true,
        'client_cap' => 58,
        'order_lead_days' => 7,
        'invoice_period' => 'monthly',
        'week_starts_on' => 'monday',
        'document_prefix_receipt' => 'REC',
        'document_prefix_purchase_order' => 'BC',
        'document_prefix_proforma' => 'PRO',
        'document_prefix_delivery_note' => 'BL',
        'document_prefix_invoice' => 'FAC',
        'document_prefix_statement' => 'BOR',
        'proforma_validity_days' => 15,
        'fne_mode' => 'not_required',
        'expense_allocation' => 'kits',
        'attachment_alert_days' => 30,
        'blocked_order_days' => 7,
        ...$overrides,
    ];
}

test('the default values come from the configuration', function () {
    $settings = app(BusinessSettings::class);

    expect($settings->get('kits_per_client'))->toBe(3);
    expect($settings->get('advance_amount'))->toBe(200000);
    expect($settings->get('advance_frequency'))->toBe('weekly');
    expect($settings->get('advance_required'))->toBeTrue();
    expect($settings->get('invoice_period'))->toBe('monthly');
    expect($settings->get('client_cap'))->toBe(0);
});

test('a stored value overrides the default and keeps its type', function () {
    $settings = app(BusinessSettings::class);

    $settings->set('kits_per_client', 5);
    $settings->set('advance_required', false);

    expect($settings->get('kits_per_client'))->toBe(5);
    expect($settings->get('advance_required'))->toBeFalse();
    expect($settings->get('advance_amount'))->toBe(200000);
});

test('a value stored in the database is read back after the cache is cleared', function () {
    app(BusinessSettings::class)->set('client_cap', 60);

    cache()->flush();

    expect(app(BusinessSettings::class)->get('client_cap'))->toBe(60);
});

test('an unknown setting is refused', function () {
    app(BusinessSettings::class)->get('does_not_exist');
})->throws(InvalidArgumentException::class);

test('only the owner can see the settings', function () {
    $this->get(route('parameters.edit'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get(route('parameters.edit'))->assertForbidden();
    $this->actingAs(User::factory()->agent()->create())->get(route('parameters.edit'))->assertForbidden();
});

test('the owner sees the current values', function () {
    app(BusinessSettings::class)->set('kits_per_client', 4);

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('parameters.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parameters/edit')
            ->where('values.kits_per_client', 4)
            ->where('values.advance_amount', 200000)
            ->has('definitions.advance_frequency.options'));
});

test('the owner updates the settings', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->patch(route('parameters.update'), validSettingsPayload([
            'kits_per_client' => 4,
            'advance_amount' => 150000,
            'advance_frequency' => 'monthly',
            'advance_required' => false,
            'client_cap' => 60,
            'invoice_period' => 'weekly',
            'company_name' => 'Zebe SARL',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('parameters.edit'));

    $settings = app(BusinessSettings::class);

    expect($settings->get('kits_per_client'))->toBe(4);
    expect($settings->get('advance_amount'))->toBe(150000);
    expect($settings->get('advance_frequency'))->toBe('monthly');
    expect($settings->get('advance_required'))->toBeFalse();
    expect($settings->get('company_name'))->toBe('Zebe SARL');
});

test('invalid settings are rejected and nothing is saved', function (array $changes, string $field) {
    $this->actingAs(User::factory()->owner()->create())
        ->patch(route('parameters.update'), validSettingsPayload($changes))
        ->assertSessionHasErrors($field);

    expect(app(BusinessSettings::class)->get('kits_per_client'))->toBe(3);
})->with([
    'no kit' => [['kits_per_client' => 0], 'kits_per_client'],
    'negative advance' => [['advance_amount' => -1], 'advance_amount'],
    'unknown frequency' => [['advance_frequency' => 'daily'], 'advance_frequency'],
    'unknown invoice period' => [['invoice_period' => 'yearly'], 'invoice_period'],
    'negative cap' => [['client_cap' => -1], 'client_cap'],
]);

test('an employee cannot update the settings', function () {
    $this->actingAs(User::factory()->create())
        ->patch(route('parameters.update'), ['kits_per_client' => 9])
        ->assertForbidden();

    expect(app(BusinessSettings::class)->get('kits_per_client'))->toBe(3);
});
