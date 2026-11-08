<?php

use App\Actions\Users\InviteUser;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
});

/** A user the owner created and who has not chosen a password yet. */
function pendingUser(): User
{
    return User::factory()->create(['password_set_at' => null, 'email' => 'invited@example.com']);
}

/** A token as the invitation e-mail carries it. */
function invitationToken(User $user): string
{
    return Password::broker(InviteUser::BROKER)->createToken($user);
}

function acceptPayload(User $user, string $token, array $overrides = []): array
{
    return [
        'token' => $token,
        'email' => $user->email,
        'password' => 'Chosen-Passw0rd-987',
        'password_confirmation' => 'Chosen-Passw0rd-987',
        ...$overrides,
    ];
}

test('creating a user sends the invitation e-mail with a link that lasts 48 hours', function () {
    Notification::fake();

    $this->actingAs($this->owner)->post(route('users.store'), ['name' => 'Aya Kone', 'email' => 'aya@example.com', 'role' => 'agent'])
        ->assertSessionHasNoErrors();

    $user = User::where('email', 'aya@example.com')->firstOrFail();

    Notification::assertSentTo($user, AccountInvitation::class, fn (AccountInvitation $mail) => $mail->validHours === 48
        && str_contains($mail->url, '/invitation/')
        && str_contains($mail->url, urlencode('aya@example.com')));
    expect(AuditLog::where('action', 'user.invited')->count())->toBe(1);
});

test('the e-mail does not say any password, and the link is not in the audit log', function () {
    Notification::fake();

    $this->actingAs($this->owner)->post(route('users.store'), ['name' => 'Aya Kone', 'email' => 'aya@example.com', 'role' => 'agent']);

    $user = User::where('email', 'aya@example.com')->firstOrFail();
    $rendered = (new AccountInvitation('https://example.test/invitation/abc', 48))->toMail($user)->render()->toHtml();

    expect($rendered)->toContain('Aya Kone')->toContain('48');
    expect(AuditLog::query()->get()->toJson())->not->toContain('/invitation/');
});

test('an invited person cannot log in before choosing a password', function () {
    Notification::fake();

    $this->actingAs($this->owner)->post(route('users.store'), ['name' => 'Aya Kone', 'email' => 'aya@example.com', 'role' => 'agent']);
    auth()->logout();

    $this->post(route('login.store'), ['email' => 'aya@example.com', 'password' => 'password'])->assertSessionHasErrors();
    $this->assertGuest();
});

test('the invited person chooses their password with the link and can then log in', function () {
    $user = pendingUser();
    $token = invitationToken($user);

    $this->get(route('invitation.show', ['token' => $token, 'email' => $user->email]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/accept-invitation')->where('email', $user->email));

    $this->post(route('invitation.accept'), acceptPayload($user, $token))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    $user->refresh();

    expect(Hash::check('Chosen-Passw0rd-987', $user->password))->toBeTrue();
    expect($user->password_set_at)->not->toBeNull();
    expect(AuditLog::where('action', 'user.invitation_accepted')->count())->toBe(1);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Chosen-Passw0rd-987']);
    $this->assertAuthenticatedAs($user);
});

test('a link works once', function () {
    $user = pendingUser();
    $token = invitationToken($user);

    $this->post(route('invitation.accept'), acceptPayload($user, $token))->assertSessionHasNoErrors();
    $this->post(route('invitation.accept'), acceptPayload($user, $token, ['password' => 'Another-Passw0rd-1', 'password_confirmation' => 'Another-Passw0rd-1']))
        ->assertSessionHasErrors('email');

    expect(Hash::check('Chosen-Passw0rd-987', $user->refresh()->password))->toBeTrue();
});

test('a link expires after 48 hours but not before', function () {
    $user = pendingUser();
    $token = invitationToken($user);

    $this->travel(47)->hours();
    $this->post(route('invitation.accept'), acceptPayload($user, $token, ['password' => 'weak', 'password_confirmation' => 'weak']))->assertSessionHasErrors('password');

    $this->travel(2)->hours();
    $this->post(route('invitation.accept'), acceptPayload($user, $token))->assertSessionHasErrors('email');

    expect($user->refresh()->password_set_at)->toBeNull();
});

test('a link of another account or a wrong token does nothing', function () {
    $user = pendingUser();
    $other = User::factory()->create(['email' => 'other@example.com']);
    $token = invitationToken($user);

    $this->post(route('invitation.accept'), acceptPayload($other, $token))->assertSessionHasErrors('email');
    $this->post(route('invitation.accept'), acceptPayload($user, 'not-the-token'))->assertSessionHasErrors('email');

    expect($user->refresh()->password_set_at)->toBeNull();
    expect(Hash::check('Chosen-Passw0rd-987', $other->refresh()->password))->toBeFalse();
});

test('the password chosen must be strong and confirmed', function (array $overrides) {
    $user = pendingUser();

    $this->post(route('invitation.accept'), acceptPayload($user, invitationToken($user), $overrides))->assertSessionHasErrors('password');
})->with([
    'weak' => [['password' => 'abc', 'password_confirmation' => 'abc']],
    'not confirmed' => [['password_confirmation' => 'different']],
]);

test('the page of a link is never kept or sent on to other sites', function () {
    $user = pendingUser();

    $this->get(route('invitation.show', ['token' => invitationToken($user), 'email' => $user->email]))
        ->assertHeader('Referrer-Policy', 'no-referrer');
});

test('the owner sends a new invitation, which replaces the previous link', function () {
    Notification::fake();
    $user = pendingUser();
    $old = invitationToken($user);

    $this->actingAs($this->owner)->post(route('users.invitation', $user))->assertRedirect();

    Notification::assertSentTo($user, AccountInvitation::class);

    auth()->logout();
    $this->post(route('invitation.accept'), acceptPayload($user, $old))->assertSessionHasErrors('email');
});

test('the owner can show a link to hand over, and it is written to the audit log', function () {
    $user = pendingUser();

    $this->actingAs($this->owner)->from(route('users.edit', $user))->post(route('users.invitation-link', $user))->assertRedirect();

    expect(AuditLog::where('action', 'user.invitation_link_shown')->count())->toBe(1);
    expect(AuditLog::query()->get()->toJson())->not->toContain('/invitation/');
});

test('the link handed over really works', function () {
    $user = pendingUser();
    $url = app(InviteUser::class)->link($user);

    $this->get($url)->assertOk();
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $token = basename((string) parse_url($url, PHP_URL_PATH));

    $this->post(route('invitation.accept'), acceptPayload($user, $token))->assertSessionHasNoErrors();
    expect($query['email'])->toBe($user->email);
});

test('an owner cannot send an invitation to themselves, and only the owner can send one', function () {
    $user = pendingUser();

    $this->actingAs($this->owner)->post(route('users.invitation', $this->owner))->assertForbidden();
    $this->actingAs($this->owner)->post(route('users.invitation-link', $this->owner))->assertForbidden();

    $agent = User::factory()->create();
    $this->actingAs($agent)->post(route('users.invitation', $user))->assertForbidden();
    $this->actingAs($agent)->post(route('users.invitation-link', $user))->assertForbidden();
});

test('when the mail cannot be sent, the account exists and the owner is sent to hand the link over', function () {
    Notification::shouldReceive('send')->andThrow(new RuntimeException('smtp down'));
    $this->withoutExceptionHandling([RuntimeException::class]);

    $response = $this->actingAs($this->owner)->post(route('users.store'), ['name' => 'Aya Kone', 'email' => 'aya@example.com', 'role' => 'agent']);

    $user = User::where('email', 'aya@example.com')->firstOrFail();
    $response->assertRedirect(route('users.edit', $user));
    expect($user->password_set_at)->toBeNull();
});

test('the list says who has not chosen a password yet', function () {
    pendingUser();

    $this->actingAs($this->owner)->get(route('users.index'))
        ->assertInertia(fn (Assert $page) => $page->where('users', fn ($users) => collect($users)->contains('invitation_pending', true)));
});

test('the date of the last invitation e-mail is kept and shown to the owner, and stays empty when nothing left', function () {
    Notification::fake();
    $user = pendingUser();

    $this->actingAs($this->owner)->get(route('users.edit', $user))
        ->assertInertia(fn (Assert $page) => $page->where('editedUser.invitation_sent_at', null));

    $this->post(route('users.invitation', $user))->assertRedirect();

    $this->get(route('users.edit', $user))
        ->assertInertia(fn (Assert $page) => $page->where('editedUser.invitation_sent_at', fn ($date) => $date !== null));
    expect($user->refresh()->invitation_sent_at)->not->toBeNull();
});
