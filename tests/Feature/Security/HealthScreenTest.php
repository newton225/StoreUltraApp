<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Support\HealthReport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The detail of one check, by key.
 *
 * @param  array<int, array<string, mixed>>  $checks
 * @return array<string, mixed>
 */
function healthCheck(array $checks, string $key): array
{
    return collect($checks)->firstWhere('key', $key);
}

test('only the owner opens the health screen', function () {
    $this->actingAs(User::factory()->create())->get(route('health.index'))->assertForbidden();

    $this->actingAs(User::factory()->owner()->create())->get(route('health.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('health/index')
            ->has('checks', 8)
            ->has('summary.errors')
            ->has('summary.warnings'));
});

test('a backup that was never made or is not set up is an error, a recent one is good', function () {
    config(['backup.password' => '']);
    expect(healthCheck(app(HealthReport::class)->checks(), 'backup'))->toMatchArray(['status' => 'error', 'detail' => 'not_configured']);

    config(['backup.password' => 'a-long-test-password', 'backup.disk' => 'remote', 'filesystems.disks.remote.driver' => 's3']);
    expect(healthCheck(app(HealthReport::class)->checks(), 'backup'))->toMatchArray(['status' => 'error', 'detail' => 'never']);

    $subject = new AuditLog;
    $subject->id = 0;
    AuditLog::record('backup.created', $subject, [], ['file' => 'x.zip', 'size' => 2097152]);

    expect(healthCheck(app(HealthReport::class)->checks(), 'backup'))->toMatchArray(['status' => 'ok', 'detail' => 'ok']);

    config(['backup.disk' => 'backups']);
    expect(healthCheck(app(HealthReport::class)->checks(), 'backup'))->toMatchArray(['status' => 'warning', 'detail' => 'local_disk']);
});

test('a scheduler that left no heartbeat, or an old one, is an error', function () {
    Cache::forget(HealthReport::SCHEDULER_KEY);
    expect(healthCheck(app(HealthReport::class)->checks(), 'scheduler'))->toMatchArray(['status' => 'error', 'detail' => 'never']);

    Cache::forever(HealthReport::SCHEDULER_KEY, now()->subMinutes(30)->timestamp);
    expect(healthCheck(app(HealthReport::class)->checks(), 'scheduler'))->toMatchArray(['status' => 'error', 'detail' => 'stopped']);

    Cache::forever(HealthReport::SCHEDULER_KEY, now()->timestamp);
    expect(healthCheck(app(HealthReport::class)->checks(), 'scheduler'))->toMatchArray(['status' => 'ok']);
});

test('the audit journal check follows the result of the daily verification', function () {
    Cache::forget(HealthReport::AUDIT_KEY);
    expect(healthCheck(app(HealthReport::class)->checks(), 'audit_chain'))->toMatchArray(['status' => 'warning', 'detail' => 'never']);

    $this->artisan('app:verify-audit-log')->assertSuccessful();
    expect(healthCheck(app(HealthReport::class)->checks(), 'audit_chain'))->toMatchArray(['status' => 'ok']);

    Cache::forever(HealthReport::AUDIT_KEY, ['at' => now()->timestamp, 'ok' => false, 'entries' => 3]);
    expect(healthCheck(app(HealthReport::class)->checks(), 'audit_chain'))->toMatchArray(['status' => 'error', 'detail' => 'broken']);
});

test('failed jobs and an unsent mailer are reported', function () {
    config(['queue.default' => 'database', 'mail.default' => 'log']);
    DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);

    $checks = app(HealthReport::class)->checks();

    expect(healthCheck($checks, 'queue'))->toMatchArray(['status' => 'warning', 'detail' => 'failed'])
        ->and(healthCheck($checks, 'mail'))->toMatchArray(['status' => 'warning', 'detail' => 'not_sending']);
});

test('the screen never carries a secret', function () {
    config(['backup.password' => 'secret-backup-password', 'backup.database.password' => 'secret-db-password']);

    $payload = json_encode(app(HealthReport::class)->checks());

    expect($payload)->not->toContain('secret-backup-password')->not->toContain('secret-db-password');
});
