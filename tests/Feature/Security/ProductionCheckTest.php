<?php

use App\Support\ProductionCheck;
use Illuminate\Support\Facades\DB;

/**
 * The settings of a server that is safe to expose.
 */
function useSafeProductionSettings(): void
{
    config([
        'app.debug' => false,
        'app.url' => 'https://store.example.ci',
        'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
        'logging.default' => 'stack',
        'logging.channels.stack.driver' => 'stack',
        'logging.channels.stack.channels' => ['single'],
        'logging.channels.single.level' => 'error',
        'security.require_owner_two_factor' => true,
        'security.audit_reference_data' => true,
        'security.access_log' => true,
        'security.csp' => 'enforce',
        'security.trusted_proxies' => ['10.0.0.5'],
        'session.secure' => true,
        'session.http_only' => true,
        'session.encrypt' => true,
        'database.default' => 'pgsql',
        'database.connections.pgsql.username' => 'storeultra_app',
        'mail.default' => 'smtp',
    ]);
}

beforeEach(function () {
    $this->testConnection = config('database.default');

    useSafeProductionSettings();
});

afterEach(function () {
    config(['database.default' => $this->testConnection]);
});

test('a safe configuration has nothing to report', function () {
    expect(app(ProductionCheck::class)->run())->toBe(['errors' => [], 'warnings' => []]);
    app(ProductionCheck::class)->abortIfUnsafe();
});

test('each dangerous setting is an error', function (string $key, mixed $value, string $fragment) {
    config([$key => $value]);

    $errors = app(ProductionCheck::class)->run()['errors'];

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain($fragment);
})->with([
    'debug on' => ['app.debug', true, 'APP_DEBUG'],
    'no https' => ['app.url', 'http://store.example.ci', 'https://'],
    'no key' => ['app.key', '', 'APP_KEY'],
    'debug log level' => ['logging.channels.single.level', 'debug', 'debug level'],
    'no owner second factor' => ['security.require_owner_two_factor', false, 'SECURITY_REQUIRE_OWNER_TWO_FACTOR'],
    'no audit of reference data' => ['security.audit_reference_data', false, 'SECURITY_AUDIT_REFERENCE_DATA'],
    'no access journal' => ['security.access_log', false, 'SECURITY_ACCESS_LOG'],
    'no content security policy' => ['security.csp', 'off', 'SECURITY_CSP'],
    'cookie over http' => ['session.secure', false, 'SESSION_SECURE_COOKIE'],
    'sessions not encrypted' => ['session.encrypt', false, 'SESSION_ENCRYPT'],
    'cookie readable by scripts' => ['session.http_only', false, 'SESSION_HTTP_ONLY'],
    'every proxy trusted' => ['security.trusted_proxies', ['*'], 'TRUSTED_PROXIES'],
    'sqlite' => ['database.default', 'sqlite', 'SQLite'],
]);

test('a log level read from a single channel is checked too', function () {
    config(['logging.default' => 'single', 'logging.channels.single.level' => 'debug']);

    expect(app(ProductionCheck::class)->run()['errors'])->toHaveCount(1);
});

test('what only deserves a look is a warning and does not stop the server', function () {
    config([
        'security.csp' => 'report-only',
        'security.trusted_proxies' => [],
        'database.connections.pgsql.username' => 'postgres',
        'mail.default' => 'log',
    ]);

    $result = app(ProductionCheck::class)->run();

    expect($result['errors'])->toBe([]);
    expect($result['warnings'])->toHaveCount(4);
    app(ProductionCheck::class)->abortIfUnsafe();
});

test('an unsafe configuration stops the server and says what to change', function () {
    config(['app.debug' => true, 'session.secure' => false]);

    expect(fn () => app(ProductionCheck::class)->abortIfUnsafe())
        ->toThrow(RuntimeException::class, 'APP_DEBUG');
});

test('the command fails on a dangerous setting and passes on a safe configuration', function () {
    // The role of the connection is read from the real database (a super-user on a developer machine): not what this test is about.
    $this->partialMock(ProductionCheck::class, fn ($mock) => $mock->shouldReceive('databaseRole')->andReturn(['errors' => [], 'warnings' => []]));

    $this->artisan('app:check-production')->assertSuccessful();

    config(['app.debug' => true]);

    $this->artisan('app:check-production')->assertFailed();
});

test('the role of the database connection is read from the database itself, only on PostgreSQL', function () {
    config(['database.default' => $this->testConnection]);

    $result = app(ProductionCheck::class)->databaseRole();

    if (DB::connection()->getDriverName() !== 'pgsql') {
        expect($result)->toBe(['errors' => [], 'warnings' => []]);

        return;
    }

    $isSuperUser = (bool) DB::selectOne('select rolsuper from pg_roles where rolname = current_user')->rolsuper;

    expect($result['errors'] !== [])->toBe($isSuperUser);
});

test('a database that cannot be asked is reported, never fatal', function () {
    config(['database.default' => 'nowhere', 'database.connections.nowhere' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => '1', 'database' => 'x', 'username' => 'x', 'password' => 'x']]);

    $result = app(ProductionCheck::class)->databaseRole();

    expect($result['errors'])->toBe([])->and($result['warnings'])->toHaveCount(1);
});

test('the command fails when the application connects as a super-user', function () {
    $this->partialMock(ProductionCheck::class, fn ($mock) => $mock->shouldReceive('databaseRole')->andReturn(['errors' => ['The application connects to the database as a super-user (postgres).'], 'warnings' => []]));

    $this->artisan('app:check-production')->assertFailed();
});

test('a migration that was deployed but not run is reported', function () {
    config(['database.default' => $this->testConnection]);

    expect(app(ProductionCheck::class)->pendingMigrations())->toBe([]);

    DB::table('migrations')->where('migration', '2026_11_07_120000_give_clients_menu_to_existing_profiles')->delete();

    expect(app(ProductionCheck::class)->pendingMigrations())->toHaveCount(1)
        ->and(app(ProductionCheck::class)->pendingMigrations()[0])->toContain('1 migration(s) not run yet')->toContain('give_clients_menu');
});
