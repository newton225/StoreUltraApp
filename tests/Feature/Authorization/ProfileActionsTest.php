<?php

use App\Enums\Permission;
use App\Enums\PermissionAction;
use App\Models\Profile;
use App\Models\Shop;
use App\Models\User;

/**
 * A profile with a menu and only some of its actions.
 *
 * @param  list<PermissionAction>  $actions
 */
function userWithActions(Permission $menu, array $actions): User
{
    $profile = Profile::factory()->create([
        'permissions' => [$menu->value, ...array_map(fn (PermissionAction $action): string => $action->value, $actions)],
    ]);

    return User::factory()->create(['profile_id' => $profile->id])->refresh();
}

test('an action is only granted when the profile lists it', function () {
    $user = userWithActions(Permission::ManageOrders, [PermissionAction::OrdersCreate]);

    expect($user->canDo(PermissionAction::OrdersCreate))->toBeTrue()
        ->and($user->canDo(PermissionAction::OrdersCancel))->toBeFalse()
        ->and($user->can('orders.create'))->toBeTrue()
        ->and($user->can('orders.cancel'))->toBeFalse();
});

test('the owner may do every action', function () {
    $owner = User::factory()->owner()->create();

    foreach (PermissionAction::cases() as $action) {
        expect($owner->canDo($action))->toBeTrue();
    }
});

test('every action belongs to one menu of the closed catalogue', function () {
    expect(PermissionAction::values())->toHaveCount(count(array_unique(PermissionAction::values())));

    foreach (PermissionAction::cases() as $action) {
        expect(str_starts_with($action->value, explode('.', $action->value)[0].'.'))->toBeTrue()
            ->and(PermissionAction::ofMenu($action->menu()))->toContain($action);
    }
});

test('a purchase action missing from the profile is refused on its route', function () {
    $user = userWithActions(Permission::ManagePurchases, [PermissionAction::PurchasesGenerate]);

    $this->actingAs($user)->get(route('purchases.index'))->assertOk();
    $this->actingAs($user)->post(route('purchases.generate'), ['week' => now()->startOfWeek()->toDateString()])->assertRedirect();
    $this->actingAs($user)->post(route('shops.payments.store', Shop::factory()->create()), [])->assertForbidden();
});

test('cancelling an order needs the cancel action, not just the orders menu', function () {
    $user = userWithActions(Permission::ManageOrders, [PermissionAction::OrdersAdvance]);
    $order = realOrder();

    $this->actingAs($user)
        ->post(route('orders.transition', $order), ['to' => 'cancelled', 'reason' => 'Doublon'])
        ->assertForbidden();
});

test('a profile form accepts actions and refuses unknown ones', function () {
    $owner = User::factory()->owner()->create();
    $payload = [
        'label_fr' => 'Caissier', 'label_en' => 'Cashier', 'restricted_to_zones' => false,
        'permissions' => [Permission::ManageCash->value, PermissionAction::CashRecord->value],
    ];

    $this->actingAs($owner)->post(route('profiles.store'), $payload)->assertRedirect(route('profiles.index'));
    expect(Profile::query()->where('label_fr', 'Caissier')->firstOrFail()->permissions)->toContain('cash.record');

    $this->actingAs($owner)->post(route('profiles.store'), [...$payload, 'label_fr' => 'Autre', 'permissions' => ['cash.everything']])
        ->assertSessionHasErrors('permissions.0');
});
