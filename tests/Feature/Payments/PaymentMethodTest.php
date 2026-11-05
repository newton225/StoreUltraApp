<?php

use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\InitialPaymentMethodsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function methodPayload(array $overrides = []): array
{
    return [
        'code' => 'cheque',
        'label_fr' => 'Chèque',
        'label_en' => 'Cheque',
        'requires_reference' => true,
        ...$overrides,
    ];
}

test('guests are sent to the login page', function () {
    $this->get(route('payment-methods.index'))->assertRedirect(route('login'));
});

test('only the owner manages the payment methods', function () {
    $method = PaymentMethod::factory()->create();

    foreach ([User::factory()->create(), User::factory()->agent()->create()] as $user) {
        $this->actingAs($user);

        $this->get(route('payment-methods.index'))->assertForbidden();
        $this->post(route('payment-methods.store'), methodPayload())->assertForbidden();
        $this->patch(route('payment-methods.update', $method), methodPayload())->assertForbidden();
    }

    expect(PaymentMethod::count())->toBe(1);
});

test('the owner sees the methods and creates one', function () {
    PaymentMethod::factory()->count(2)->create();
    $this->actingAs(User::factory()->owner()->create());

    $this->get(route('payment-methods.index'))
        ->assertInertia(fn (Assert $page) => $page->component('payment-methods/index')->has('methods', 2));

    $this->post(route('payment-methods.store'), methodPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('payment-methods.index'));

    $method = PaymentMethod::where('code', 'cheque')->firstOrFail();

    expect($method->label_fr)->toBe('Chèque');
    expect($method->requires_reference)->toBeTrue();
    expect($method->active)->toBeTrue();
});

test('a method is rejected for invalid data', function (array $overrides, string $field) {
    PaymentMethod::factory()->create(['code' => 'cash']);

    $this->actingAs(User::factory()->owner()->create())
        ->post(route('payment-methods.store'), methodPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(PaymentMethod::count())->toBe(1);
})->with([
    'duplicate code' => [['code' => 'cash'], 'code'],
    'bad characters in the code' => [['code' => 'my code!'], 'code'],
    'missing french label' => [['label_fr' => ''], 'label_fr'],
    'missing english label' => [['label_en' => ''], 'label_en'],
]);

test('the owner renames and deactivates a method, and its code stays', function () {
    $method = PaymentMethod::factory()->create(['code' => 'cash']);

    $this->actingAs(User::factory()->owner()->create())
        ->patch(route('payment-methods.update', $method), methodPayload([
            'code' => 'cash',
            'label_fr' => 'Espèces',
            'active' => false,
        ]))
        ->assertSessionHasNoErrors();

    $method->refresh();

    expect($method->label_fr)->toBe('Espèces');
    expect($method->active)->toBeFalse();
    expect($method->code)->toBe('cash');
});

test('the name follows the language of the interface', function () {
    $method = PaymentMethod::factory()->create(['label_fr' => 'Espèces', 'label_en' => 'Cash']);

    app()->setLocale('en');
    expect($method->name)->toBe('Cash');

    app()->setLocale('fr');
    expect($method->name)->toBe('Espèces');
});

test('the seeder creates the usual methods once', function () {
    $this->seed(InitialPaymentMethodsSeeder::class);
    $this->seed(InitialPaymentMethodsSeeder::class);

    expect(PaymentMethod::count())->toBe(3);
    expect(PaymentMethod::where('code', 'cash')->sole()->requires_reference)->toBeFalse();
    expect(PaymentMethod::where('code', 'mobile_money')->sole()->requires_reference)->toBeTrue();
    expect(PaymentMethod::where('code', 'transfer')->sole()->requires_reference)->toBeTrue();
});

test('the internal code is made from the French name when none is typed, and kept unique', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('payment-methods.store'), ['label_fr' => 'Mobile Money Orange', 'label_en' => 'Orange Money'])->assertSessionHasNoErrors();
    $this->post(route('payment-methods.store'), ['label_fr' => 'Mobile Money Orange', 'label_en' => 'Orange Money bis'])->assertSessionHasNoErrors();

    expect(PaymentMethod::query()->whereIn('code', ['mobile_money_orange', 'mobile_money_orange_2'])->count())->toBe(2);
});
