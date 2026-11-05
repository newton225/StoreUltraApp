<?php

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\AuditChain;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Switch off what protects the journal for the length of a test, to play the part of someone who
 * reaches the database directly. The change is undone with the test, like everything else in it.
 */
function reachTheDatabaseDirectly(): void
{
    if (DB::getDriverName() === 'sqlite') {
        DB::unprepared('DROP TRIGGER audit_logs_no_update');
        DB::unprepared('DROP TRIGGER audit_logs_no_delete');
    } else {
        DB::unprepared('ALTER TABLE audit_logs DISABLE TRIGGER USER');
    }
}

function writeEntries(int $count = 3): void
{
    $client = Client::factory()->create();

    foreach (range(1, $count) as $number) {
        AuditLog::record('client.example', $client, ['step' => $number - 1], ['step' => $number]);
    }
}

test('an entry carries the address and the browser of the request that caused it', function () {
    $this->actingAs(User::factory()->create())
        ->withHeader('User-Agent', 'TestBrowser/1.0')
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->post(route('orders.payments.store', payableOrder()), paymentPayload(PaymentMethod::factory()->create(['requires_reference' => false])));

    $entry = AuditLog::where('action', 'payment.recorded')->sole();

    expect($entry->ip_address)->toBe('203.0.113.7');
    expect($entry->user_agent)->toBe('TestBrowser/1.0');
    expect($entry->hash)->toMatch('/^[0-9a-f]{64}$/');
});

test('each entry is chained to the one before it', function () {
    writeEntries(3);
    [$first, $second, $third] = AuditLog::orderBy('id')->get()->all();

    $expected = fn (AuditLog $entry, ?string $previous): string => AuditChain::hash($previous, $entry->user_id, $entry->action, $entry->subject_type, $entry->subject_id, $entry->before, $entry->after, $entry->created_at->format('Y-m-d H:i:s'));

    expect($first->hash)->toBe($expected($first, AuditChain::START));
    expect($second->hash)->toBe($expected($second, $first->hash));
    expect($third->hash)->toBe($expected($third, $second->hash));
    expect(DB::table('audit_chain')->value('last_hash'))->toBe($third->hash);
});

test('an intact journal passes the check', function () {
    writeEntries(4);

    $this->artisan('app:verify-audit-log')->assertSuccessful();
});

test('an empty journal passes the check', function () {
    $this->artisan('app:verify-audit-log')->assertSuccessful();
});

test('the database itself refuses to change or remove an entry', function () {
    writeEntries(1);

    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->update(['action' => 'forged'])))->toThrow(QueryException::class, 'can only be added to');
    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->delete()))->toThrow(QueryException::class, 'can only be added to');
    expect(AuditLog::count())->toBe(1);
    expect(AuditLog::sole()->action)->toBe('client.example');
});

test('on PostgreSQL the whole table cannot be emptied either', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('TRUNCATE exists on PostgreSQL only.');
    }

    writeEntries(1);

    expect(fn () => DB::unprepared('TRUNCATE audit_logs'))->toThrow(QueryException::class);
});

test('a changed entry is detected', function () {
    writeEntries(3);
    reachTheDatabaseDirectly();

    DB::table('audit_logs')->orderBy('id')->limit(1)->update(['after' => json_encode(['step' => 99])]);

    $this->artisan('app:verify-audit-log')->assertFailed();
});

test('an entry removed from the middle is detected', function () {
    writeEntries(3);
    reachTheDatabaseDirectly();

    DB::table('audit_logs')->where('id', AuditLog::orderBy('id')->skip(1)->value('id'))->delete();

    $this->artisan('app:verify-audit-log')->assertFailed();
});

test('entries removed from the end are detected by the head of the chain', function () {
    writeEntries(3);
    reachTheDatabaseDirectly();

    DB::table('audit_logs')->where('id', AuditLog::max('id'))->delete();

    $this->artisan('app:verify-audit-log')->assertFailed();
});

test('an entry slipped in by hand is detected', function () {
    writeEntries(2);
    reachTheDatabaseDirectly();

    DB::table('audit_logs')->insert([
        'user_id' => null,
        'action' => 'forged.entry',
        'subject_type' => Client::class,
        'subject_id' => 1,
        'created_at' => now()->toDateTimeString(),
    ]);

    $this->artisan('app:verify-audit-log')->assertFailed();
});

test('erasing the address of an entry does not break the chain', function () {
    $this->actingAs(User::factory()->create())
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->post(route('orders.payments.store', payableOrder()), paymentPayload(PaymentMethod::factory()->create(['requires_reference' => false])));
    writeEntries(2);
    reachTheDatabaseDirectly();

    DB::table('audit_logs')->update(['ip_address' => null, 'user_agent' => null]);

    $this->artisan('app:verify-audit-log')->assertSuccessful();
});

test('an entry whose action is rolled back leaves no trace and no hole in the chain', function () {
    writeEntries(2);
    $client = Client::factory()->create();

    expect(fn () => DB::transaction(function () use ($client) {
        AuditLog::record('client.lost', $client, [], ['x' => 1]);

        throw new RuntimeException('the action failed');
    }))->toThrow(RuntimeException::class);

    AuditLog::record('client.kept', $client, [], ['x' => 2]);

    expect(AuditLog::where('action', 'client.lost')->exists())->toBeFalse();
    $this->artisan('app:verify-audit-log')->assertSuccessful();
});

test('deleting a user leaves the journal as it was', function () {
    $user = User::factory()->create();
    AuditLog::record('client.example', Client::factory()->create(), [], ['x' => 1], $user);

    $user->delete();

    $entry = AuditLog::sole();
    expect($entry->user_id)->toBe($user->id);
    expect($entry->user)->toBeNull();
    $this->artisan('app:verify-audit-log')->assertSuccessful();
});

test('the journal page shows the address of each entry', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->post(route('orders.payments.store', payableOrder()), paymentPayload(PaymentMethod::factory()->create(['requires_reference' => false])));

    $this->get(route('audit.index'))
        ->assertInertia(fn (Assert $page) => $page->where('entries.data.0.ip', '203.0.113.9'));
});

test('entries written before the chain existed are chained when it is added', function () {
    $migration = 'database/migrations/2026_10_29_144802_harden_audit_logs_table.php';

    Artisan::call('migrate:rollback', ['--path' => $migration, '--force' => true]);

    foreach (['2026-01-05 09:00:00', '2026-01-06 10:30:00'] as $at) {
        DB::table('audit_logs')->insert([
            'user_id' => null,
            'action' => 'legacy.entry',
            'subject_type' => Client::class,
            'subject_id' => 7,
            'before' => null,
            'after' => json_encode(['name' => 'Aya Koné']),
            'created_at' => $at,
        ]);
    }

    Artisan::call('migrate', ['--path' => $migration, '--force' => true]);

    expect(DB::table('audit_logs')->whereNull('hash')->count())->toBe(0);
    $this->artisan('app:verify-audit-log')->assertSuccessful();

    AuditLog::record('client.new', Client::factory()->create(), [], ['x' => 1]);
    $this->artisan('app:verify-audit-log')->assertSuccessful();
});

test('the check runs every day', function () {
    $scheduled = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'app:verify-audit-log'));

    expect($scheduled)->not->toBeNull();
    expect($scheduled->expression)->toBe('0 2 * * *');
});
