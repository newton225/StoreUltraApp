<?php

use App\Models\ExpenseType;
use App\Models\User;
use Database\Seeders\InitialExpenseTypesSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function typePayload(array $overrides = []): array
{
    return ['code' => 'fuel', 'label_fr' => 'Carburant', 'label_en' => 'Fuel', ...$overrides];
}

test('only the owner manages the expense types', function () {
    $type = ExpenseType::factory()->create();

    foreach ([User::factory()->create(), User::factory()->agent()->create()] as $user) {
        $this->actingAs($user);

        $this->get(route('expense-types.index'))->assertForbidden();
        $this->post(route('expense-types.store'), typePayload())->assertForbidden();
        $this->patch(route('expense-types.update', $type), typePayload())->assertForbidden();
    }

    expect(ExpenseType::count())->toBe(1);
});

test('the owner lists, creates and updates the types', function () {
    ExpenseType::factory()->count(2)->create();
    $this->actingAs(User::factory()->owner()->create());

    $this->get(route('expense-types.index'))
        ->assertInertia(fn (Assert $page) => $page->component('expense-types/index')->has('types', 2));

    $this->post(route('expense-types.store'), typePayload())->assertSessionHasNoErrors();

    $type = ExpenseType::where('code', 'fuel')->firstOrFail();

    expect($type->active)->toBeTrue();

    $this->patch(route('expense-types.update', $type), typePayload(['code' => 'other', 'label_fr' => 'Essence', 'active' => false]))
        ->assertSessionHasNoErrors();

    $type->refresh();

    expect($type->label_fr)->toBe('Essence');
    expect($type->active)->toBeFalse();
    expect($type->code)->toBe('fuel');
});

test('a type is rejected for invalid data', function (array $overrides, string $field) {
    ExpenseType::factory()->create(['code' => 'transport']);
    $this->actingAs(User::factory()->owner()->create());

    $this->post(route('expense-types.store'), typePayload($overrides))->assertSessionHasErrors($field);

    expect(ExpenseType::count())->toBe(1);
})->with([
    'duplicate code' => [['code' => 'transport'], 'code'],
    'bad code' => [['code' => 'my code!'], 'code'],
    'missing french label' => [['label_fr' => ''], 'label_fr'],
    'missing english label' => [['label_en' => ''], 'label_en'],
]);

test('the name follows the language', function () {
    $type = ExpenseType::factory()->create(['label_fr' => 'Transport', 'label_en' => 'Transport (en)']);

    app()->setLocale('en');
    expect($type->name)->toBe('Transport (en)');

    app()->setLocale('fr');
    expect($type->name)->toBe('Transport');
});

test('the seeder creates the usual types once', function () {
    $this->seed(InitialExpenseTypesSeeder::class);
    $this->seed(InitialExpenseTypesSeeder::class);

    expect(ExpenseType::pluck('code')->sort()->values()->all())->toBe(['contingency', 'handling', 'other', 'transport']);
});
