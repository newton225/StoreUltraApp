<?php

use App\Actions\Users\InviteUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

test('deactivated users cannot log in', function () {
    $user = User::factory()->inactive()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('a user deactivated during a session is logged out on the next request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->forceFill(['is_active' => false])->save();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a deactivated account cannot sign in with a passkey either', function () {
    $passkey = new Passkey;

    $passkey->setRelation('user', User::factory()->inactive()->create());
    expect(Passkeys::allowsLogin(Request::create('/'), $passkey))->toBeFalse();

    $passkey->setRelation('user', User::factory()->create());
    expect(Passkeys::allowsLogin(Request::create('/'), $passkey))->toBeTrue();
});

test('deactivating an account cancels the link it was sent to choose its password', function () {
    $owner = User::factory()->owner()->create();
    $user = User::factory()->create(['password_set_at' => null]);
    $token = Password::broker(InviteUser::BROKER)->createToken($user);

    $this->actingAs($owner)->patch(route('users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'role' => 'employee',
        'is_active' => false,
    ])->assertSessionHasNoErrors();

    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();

    auth()->logout();
    $this->post(route('invitation.accept'), ['token' => $token, 'email' => $user->email, 'password' => 'Chosen-Passw0rd-987', 'password_confirmation' => 'Chosen-Passw0rd-987'])
        ->assertSessionHasErrors('email');
    expect($user->refresh()->password_set_at)->toBeNull();
});

test('a deactivated account cannot choose a password with a link, invitation or forgotten password', function () {
    $user = User::factory()->inactive()->create(['password_set_at' => null]);
    $payload = fn (string $token) => ['token' => $token, 'email' => $user->email, 'password' => 'Chosen-Passw0rd-987', 'password_confirmation' => 'Chosen-Passw0rd-987'];

    $invitation = Password::broker(InviteUser::BROKER)->createToken($user);
    $this->post(route('invitation.accept'), $payload($invitation))->assertSessionHasErrors('email');

    $forgotten = Password::broker()->createToken($user);
    $this->post(route('password.update'), $payload($forgotten))->assertSessionHasErrors('email');

    expect(Hash::check('Chosen-Passw0rd-987', $user->refresh()->password))->toBeFalse();
});

test('the owner cannot send an invitation to a deactivated account', function () {
    $owner = User::factory()->owner()->create();
    $user = User::factory()->inactive()->create();

    $this->actingAs($owner)->post(route('users.invitation', $user))->assertStatus(422);
    $this->actingAs($owner)->post(route('users.invitation-link', $user))->assertStatus(422);
});
