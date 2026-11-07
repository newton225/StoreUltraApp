<?php

use App\Enums\Permission;
use App\Enums\PermissionAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\User;
use App\Models\Zone;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
});

function profilePayload(array $overrides = []): array
{
    return [
        'label_fr' => 'Comptable',
        'label_en' => 'Accountant',
        'restricted_to_zones' => false,
        'permissions' => [Permission::ManageExpenses->value],
        ...$overrides,
    ];
}

test('the three system profiles exist and the owner lists them first', function () {
    $this->actingAs($this->owner)->get(route('profiles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('profiles/index')
            ->has('profiles', 3)
            ->where('profiles.0.is_system', true)
            ->where('permissions', Permission::values()));

    expect(Profile::query()->where('is_system', true)->pluck('code')->sort()->values()->all())
        ->toBe(['agent', 'employee', 'owner']);
});

test('only the owner manages profiles', function () {
    $employee = User::factory()->create();
    $custom = Profile::factory()->create();

    foreach ([$employee, User::factory()->agent()->create()] as $user) {
        $this->actingAs($user)->get(route('profiles.index'))->assertForbidden();
        $this->actingAs($user)->post(route('profiles.store'), profilePayload())->assertForbidden();
        $this->actingAs($user)->put(route('profiles.update', $custom), profilePayload())->assertForbidden();
        $this->actingAs($user)->delete(route('profiles.destroy', $custom))->assertForbidden();
    }
});

test('the owner creates a custom profile and the creation is journaled', function () {
    $this->actingAs($this->owner)->post(route('profiles.store'), profilePayload())
        ->assertRedirect(route('profiles.index'));

    $profile = Profile::query()->where('label_fr', 'Comptable')->firstOrFail();

    expect($profile->is_system)->toBeFalse()
        ->and($profile->code)->toBeNull()
        ->and($profile->permissions)->toBe([Permission::ManageExpenses->value]);
    expect(AuditLog::query()->where('action', 'profile.created')->count())->toBe(1);
});

test('a new profile can start from the rights of an existing one', function () {
    $employee = Profile::forRole(UserRole::Employee);

    $this->actingAs($this->owner)->get(route('profiles.create', ['from' => $employee->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('template.permissions', $employee->permissions)
            ->where('template.restricted_to_zones', false));
});

test('a permission outside the closed catalogue is refused', function () {
    $this->actingAs($this->owner)
        ->post(route('profiles.store'), profilePayload(['permissions' => ['manage_everything']]))
        ->assertSessionHasErrors('permissions.0');

    expect(Profile::query()->where('is_system', false)->count())->toBe(0);
});

test('system profiles cannot be changed or deleted', function () {
    $employee = Profile::forRole(UserRole::Employee);
    $permissions = $employee->permissions;

    $this->actingAs($this->owner)->get(route('profiles.edit', $employee))
        ->assertInertia(fn (Assert $page) => $page->where('profile.is_system', true));
    $this->actingAs($this->owner)->put(route('profiles.update', $employee), profilePayload())->assertForbidden();
    $this->actingAs($this->owner)->delete(route('profiles.destroy', $employee))->assertForbidden();

    expect($employee->refresh()->permissions)->toBe($permissions);
});

test('a custom profile is updated, its users follow and unrestricted users lose their zones', function () {
    $profile = Profile::factory()->restrictedToZones()->create();
    $user = User::factory()->agent()->create(['profile_id' => $profile->id]);
    $user->zones()->attach(Zone::factory()->create());

    $this->actingAs($this->owner)
        ->put(route('profiles.update', $profile), profilePayload([
            'restricted_to_zones' => false,
            'permissions' => [Permission::ManageOrders->value],
        ]))
        ->assertRedirect(route('profiles.index'));

    expect($user->refresh()->role)->toBe(UserRole::Employee)
        ->and($user->zones()->count())->toBe(0)
        ->and($user->isRestrictedToZones())->toBeFalse();
    expect(AuditLog::query()->where('action', 'profile.updated')->count())->toBe(1);
});

test('a profile in use cannot be deleted, a free one can', function () {
    $profile = Profile::factory()->create();
    $user = User::factory()->create(['profile_id' => $profile->id]);

    $this->actingAs($this->owner)->delete(route('profiles.destroy', $profile))
        ->assertSessionHasErrors('profile');
    expect(Profile::query()->whereKey($profile->id)->exists())->toBeTrue();

    $user->forceFill(['profile_id' => Profile::forRole(UserRole::Employee)->id])->save();

    $this->actingAs($this->owner)->delete(route('profiles.destroy', $profile))
        ->assertRedirect(route('profiles.index'));
    expect(Profile::query()->whereKey($profile->id)->exists())->toBeFalse();
    expect(AuditLog::query()->where('action', 'profile.deleted')->count())->toBe(1);
});

test('a user takes the rights of the profile given to them', function () {
    $purchasing = withProfile(User::factory()->create(), [Permission::ManagePurchases]);

    expect($purchasing->hasPermission(Permission::ManagePurchases))->toBeTrue()
        ->and($purchasing->hasPermission(Permission::ManageExpenses))->toBeFalse()
        ->and($purchasing->grantedPermissions())->toBe([Permission::ManagePurchases->value]);

    $this->actingAs($purchasing)->get(route('purchases.index'))->assertOk();
    $this->actingAs($purchasing)->get(route('expenses.index'))->assertForbidden();
    $this->actingAs($purchasing)->get(route('payments.index'))->assertForbidden();
});

test('a change of the rights of a profile applies to a user already loaded', function () {
    $profile = Profile::factory()->granting([Permission::ManageExpenses])->create();
    $user = User::factory()->create(['profile_id' => $profile->id]);

    expect($user->hasPermission(Permission::ManageExpenses))->toBeTrue();

    $profile->forceFill(['permissions' => []])->save();

    expect($user->hasPermission(Permission::ManageExpenses))->toBeFalse();
});

test('the owner always holds every permission and the rights are shared with the page', function () {
    expect($this->owner->grantedPermissions())->toBe(Permission::values());

    $this->actingAs(withProfile(User::factory()->create(), [Permission::ViewProfit]))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', [Permission::ViewProfit->value])
            ->where('auth.restrictedToZones', false));
});

test('managing users and settings stays reserved to the owner whatever the profile', function () {
    $user = withProfile(User::factory()->create(), Permission::cases());

    $this->actingAs($user)->get(route('users.index'))->assertForbidden();
    $this->actingAs($user)->get(route('profiles.index'))->assertForbidden();
});

test('a right is ticked and unticked straight from the table, and the change is journaled', function () {
    $profile = Profile::factory()->create();

    $this->actingAs($this->owner)
        ->patch(route('profiles.rights', $profile), ['right' => Permission::ManageOrders->value, 'granted' => true])
        ->assertRedirect();
    // A menu brings its actions with it.
    expect($profile->refresh()->permissions)->toBe([
        Permission::ManageOrders->value,
        ...array_map(fn (PermissionAction $action): string => $action->value, PermissionAction::ofMenu(Permission::ManageOrders)),
    ]);

    $this->actingAs($this->owner)
        ->patch(route('profiles.rights', $profile), ['right' => Permission::ManageOrders->value, 'granted' => false]);
    expect($profile->refresh()->permissions)->toBe([]);
    expect(AuditLog::query()->where('action', 'profile.updated')->count())->toBe(2);
});

test('unticking the restriction frees the zones of the users of the profile', function () {
    $profile = Profile::factory()->restrictedToZones()->create();
    $user = User::factory()->agent()->create(['profile_id' => $profile->id]);
    $user->zones()->attach(Zone::factory()->create());

    $this->actingAs($this->owner)
        ->patch(route('profiles.rights', $profile), ['right' => 'restricted_to_zones', 'granted' => false]);

    expect($user->refresh()->zones()->count())->toBe(0)
        ->and($user->role)->toBe(UserRole::Employee);
});

test('the table cannot change a system profile, an unknown right or be used by a non owner', function () {
    $employee = Profile::forRole(UserRole::Employee);
    $custom = Profile::factory()->create();

    $this->actingAs($this->owner)
        ->patch(route('profiles.rights', $employee), ['right' => Permission::ViewProfit->value, 'granted' => true])
        ->assertForbidden();
    $this->actingAs($this->owner)
        ->patch(route('profiles.rights', $custom), ['right' => 'manage_everything', 'granted' => true])
        ->assertSessionHasErrors('right');
    $this->actingAs(User::factory()->create())
        ->patch(route('profiles.rights', $custom), ['right' => Permission::ViewProfit->value, 'granted' => true])
        ->assertForbidden();
});
