<?php

use App\Models\User;

test('the appearance choice is saved in the profile', function (string $appearance) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('appearance.update'), ['appearance' => $appearance])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->appearance)->toBe($appearance);
})->with(['light', 'dark', 'system']);

test('an unknown appearance is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('appearance.update'), ['appearance' => 'sepia'])
        ->assertSessionHasErrors('appearance');

    expect($user->refresh()->appearance)->toBe('system');
});

test('guests cannot save an appearance', function () {
    $this->patch(route('appearance.update'), ['appearance' => 'dark'])
        ->assertRedirect(route('login'));
});

test('the saved appearance is applied before the first render', function () {
    $user = User::factory()->create(['appearance' => 'dark']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('class="dark"', false);
});

test('the old address of the appearance page leads to the profile', function () {
    $this->actingAs(User::factory()->create())
        ->get('settings/appearance')
        ->assertRedirect('/settings/profile');
});
