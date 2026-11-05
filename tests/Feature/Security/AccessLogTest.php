<?php

use App\Models\AccessLog;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;

beforeEach(function () {
    config(['security.access_log' => true]);
});

test('a page a signed-in user opens is written with the address and the browser', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->withHeaders(['User-Agent' => 'TestBrowser/1.0'])
        ->get(route('clients.index', ['search' => 'Alpha']))
        ->assertOk();

    $line = AccessLog::query()->where('kind', 'page')->latest('id')->firstOrFail();

    expect($line->user_id)->toBe($owner->id)
        ->and($line->method)->toBe('GET')
        ->and($line->route)->toBe('clients.index')
        ->and($line->path)->toBe('/clients')
        ->and($line->query)->toBe('search=Alpha')
        ->and($line->status)->toBe(200)
        ->and($line->ip_address)->toBe('127.0.0.1')
        ->and($line->user_agent)->toBe('TestBrowser/1.0');
});

test('a value that could be a secret is never kept in the query', function () {
    $this->actingAs(User::factory()->owner()->create())->get(route('clients.index', ['token' => 'abc123', 'api_key' => 'k', 'search' => 'x']));

    $line = AccessLog::query()->latest('id')->firstOrFail();

    expect($line->query)->toContain('search=x')->and($line->query)->not->toContain('abc123')->and($line->query)->not->toContain('k&');
});

test('redirects, posts and visitors who are not signed in leave no page line', function () {
    $this->get(route('login'));
    $this->get('/');
    $this->actingAs(User::factory()->owner()->create())->post(route('cash.store'), []);

    expect(AccessLog::where('kind', 'page')->count())->toBe(0);
});

test('the journal can be switched off', function () {
    config(['security.access_log' => false]);

    $this->actingAs(User::factory()->owner()->create())->get(route('clients.index'));

    expect(AccessLog::count())->toBe(0);
});

test('a failed sign-in is written with the address typed, without the password', function () {
    $this->post(route('login.store'), ['email' => 'inconnu@example.com', 'password' => 'secret-password']);

    $line = AccessLog::query()->where('kind', 'login_failed')->firstOrFail();

    expect($line->email)->toBe('inconnu@example.com')
        ->and($line->ip_address)->not->toBeNull()
        ->and(json_encode($line->getAttributes()))->not->toContain('secret-password');
});

test('sign-in, sign-out and password changes go to the audit journal with who did it', function () {
    $user = User::factory()->owner()->create();

    event(new Login('web', $user, false));
    event(new Logout('web', $user));
    event(new PasswordUpdatedViaController($user));
    event(new PasswordReset($user));

    $actions = AuditLog::query()->where('user_id', $user->id)->orderBy('id')->pluck('action')->all();

    expect($actions)->toBe(['auth.login', 'auth.logout', 'auth.password_changed', 'auth.password_reset']);
});

test('the second factor changes are traced', function () {
    $user = User::factory()->owner()->create();

    event(new TwoFactorAuthenticationEnabled($user));
    event(new TwoFactorAuthenticationDisabled($user));

    expect(AuditLog::where('subject_id', $user->id)->pluck('action')->all())->toContain('auth.two_factor_enabled', 'auth.two_factor_disabled');
});

test('a profile change is traced with the old and the new values', function () {
    $user = User::factory()->owner()->create(['name' => 'Ancien Nom']);

    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Nouveau Nom', 'email' => $user->email])->assertRedirect();

    $entry = AuditLog::query()->where('action', 'auth.profile_updated')->firstOrFail();

    expect($entry->before)->toBe(['name' => 'Ancien Nom'])->and($entry->after)->toBe(['name' => 'Nouveau Nom']);
});

test('old lines are purged, the purge is traced and recent lines stay', function () {
    config(['security.access_log_retention_days' => 30]);
    $old = AccessLog::query()->create(['kind' => 'page', 'path' => '/old']);
    $old->forceFill(['created_at' => now()->subDays(40)])->save();
    AccessLog::query()->create(['kind' => 'page', 'path' => '/recent']);

    $this->artisan('app:prune-access-logs')->assertSuccessful();

    expect(AccessLog::pluck('path')->all())->toBe(['/recent'])
        ->and(AuditLog::where('action', 'access_log.pruned')->firstOrFail()->after['deleted'])->toBe(1);
});

test('only the owner reads, filters and exports the access journal', function () {
    $owner = User::factory()->owner()->create();
    AccessLog::query()->create(['kind' => 'page', 'path' => '/clients', 'ip_address' => '10.0.0.9', 'user_id' => $owner->id]);
    AccessLog::query()->create(['kind' => 'page', 'path' => '/orders', 'ip_address' => '10.0.0.8']);

    $this->actingAs($owner)->get(route('access.index', ['ip' => '10.0.0.9']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('entries.data', 1)->where('entries.data.0.ip', '10.0.0.9'));

    $csv = $this->actingAs($owner)->get(route('list-exports.show', ['view' => 'access', 'format' => 'csv', 'ip' => '10.0.0.9']))->assertOk()->getContent();
    expect($csv)->toContain('10.0.0.9')->and($csv)->not->toContain('10.0.0.8');

    $this->actingAs(User::factory()->agent()->create())->get(route('access.index'))->assertForbidden();
});

test('a failed sign-in line exists even without a user', function () {
    event(new Failed('web', null, ['email' => 'x@example.com', 'password' => 'p']));

    expect(AccessLog::where('kind', 'login_failed')->count())->toBe(1);
});
