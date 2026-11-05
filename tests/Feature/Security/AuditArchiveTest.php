<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditArchive;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('audit-archive');
    $this->travelTo(now()->startOfMonth()->subMonths(2)->addDays(3));
});

/**
 * Two entries two months ago and one this month, chained as in real life.
 */
function journalOverThreeMonths(): string
{
    $subject = User::factory()->create();
    AuditLog::record('test.first', $subject, [], ['a' => 1]);
    test()->travel(1)->hours();
    AuditLog::record('test.second', $subject, ['a' => 1], ['a' => 2]);

    test()->travelTo(now()->addMonths(2));
    AuditLog::record('test.current', $subject, [], ['b' => 1]);

    return now()->subMonths(2)->format('Y-m');
}

test('a finished month is copied with its manifest and the copy verifies', function () {
    $month = journalOverThreeMonths();

    $this->artisan('app:archive-audit-log')->assertSuccessful();

    $manifest = collect(app(AuditArchive::class)->manifests())->firstWhere('month', $month);

    expect($manifest['count'])->toBeGreaterThanOrEqual(2)
        ->and($manifest['previous_hash'])->toBeNull()
        ->and(Storage::disk('audit-archive')->exists($manifest['file']))->toBeTrue()
        ->and(AuditLog::where('action', 'audit_archive.created')->count())->toBeGreaterThanOrEqual(1);

    $this->artisan('app:verify-audit-archive')->assertSuccessful();
});

test('the month in progress is never copied', function () {
    journalOverThreeMonths();

    $this->artisan('app:archive-audit-log', ['--month' => now()->format('Y-m')])->assertFailed();

    expect(collect(app(AuditArchive::class)->manifests())->pluck('month')->all())->not->toContain(now()->format('Y-m'));
});

test('running it again changes nothing and writes no second trace', function () {
    journalOverThreeMonths();

    $this->artisan('app:archive-audit-log')->assertSuccessful();
    $traces = AuditLog::where('action', 'audit_archive.created')->count();
    $this->artisan('app:archive-audit-log')->assertSuccessful();

    expect(AuditLog::where('action', 'audit_archive.created')->count())->toBe($traces);
});

test('an altered or missing copy is detected', function () {
    $month = journalOverThreeMonths();
    $this->artisan('app:archive-audit-log')->assertSuccessful();
    $file = "audit-{$month}.ndjson.gz";

    Storage::disk('audit-archive')->put($file, gzencode('{"id":1}'."\n"));
    $this->artisan('app:verify-audit-archive')->assertFailed();

    Storage::disk('audit-archive')->delete($file);
    $this->artisan('app:verify-audit-archive')->assertFailed();
});

test('an existing copy that no longer matches the journal is never replaced', function () {
    $month = journalOverThreeMonths();
    $this->artisan('app:archive-audit-log')->assertSuccessful();
    $name = "audit-{$month}.manifest.json";
    $manifest = json_decode(Storage::disk('audit-archive')->get($name), true);
    $manifest['content_sha256'] = str_repeat('0', 64);
    Storage::disk('audit-archive')->put($name, json_encode($manifest));

    $this->artisan('app:archive-audit-log', ['--month' => $month])->assertFailed();

    expect(json_decode(Storage::disk('audit-archive')->get($name), true)['content_sha256'])->toBe(str_repeat('0', 64));
});

test('only the owner lists and downloads the copies, and each download is traced', function () {
    $month = journalOverThreeMonths();
    $this->artisan('app:archive-audit-log')->assertSuccessful();
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->get(route('audit.index'))
        ->assertInertia(fn ($page) => $page->has('archives', 1)->where('archives.0.month', $month));

    $this->actingAs($owner)->get(route('audit.archives.download', $month))->assertOk();
    expect(AuditLog::where('action', 'audit_archive.downloaded')->count())->toBe(1);

    $this->actingAs($owner)->get(route('audit.archives.download', '1999-01'))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('audit.archives.download', $month))->assertForbidden();
});

/**
 * One entry in each of three finished months, then the three copies written.
 *
 * @return list<string> The three months, oldest first.
 */
function threeCopies(): array
{
    $subject = User::factory()->create();
    $months = [];

    $base = CarbonImmutable::now()->startOfMonth();

    foreach ([2, 1, 0] as $back) {
        test()->travelTo($base->subMonths($back)->addDays(2));
        AuditLog::record('test.month', $subject, [], ['back' => $back]);
        $months[] = now()->format('Y-m');
    }

    test()->travelTo($base->addMonths(2)->addDays(2));
    test()->artisan('app:archive-audit-log')->assertSuccessful();

    return $months;
}

test('copies older than the retention are deleted, the recent ones stay, the manifests stay', function () {
    [$oldest, $middle, $recent] = threeCopies();
    config(['security.audit_archive_retention_months' => 2, 'security.audit_archive_min_keep' => 1, 'security.audit_archive_disk_max_percent' => 0]);

    $this->artisan('app:prune-audit-archive')->assertSuccessful();

    $disk = Storage::disk('audit-archive');
    expect($disk->exists("audit-{$oldest}.ndjson.gz"))->toBeFalse()
        ->and($disk->exists("audit-{$middle}.ndjson.gz"))->toBeFalse()
        ->and($disk->exists("audit-{$recent}.ndjson.gz"))->toBeTrue()
        ->and($disk->exists("audit-{$oldest}.manifest.json"))->toBeTrue()
        ->and(AuditLog::where('action', 'audit_archive.pruned')->count())->toBe(2);

    $this->artisan('app:verify-audit-archive')->assertSuccessful();

    $owner = User::factory()->owner()->create();
    $this->actingAs($owner)->get(route('audit.archives.download', $oldest))->assertNotFound();
    $this->actingAs($owner)->get(route('audit.index'))
        ->assertInertia(fn ($page) => $page->has('archives', 3)->where('archives.2.purged_at', fn ($value) => $value !== null));
});

test('when the disk is too full the oldest copies go first, until it is not', function () {
    [$oldest, $middle, $recent] = threeCopies();
    config(['security.audit_archive_retention_months' => 120, 'security.audit_archive_min_keep' => 1, 'security.audit_archive_disk_max_percent' => 85]);
    $this->partialMock(AuditArchive::class, fn ($mock) => $mock->shouldReceive('diskUsagePercent')->andReturn(95.0, 50.0, 50.0));

    $this->artisan('app:prune-audit-archive')->assertSuccessful();

    $disk = Storage::disk('audit-archive');
    expect($disk->exists("audit-{$oldest}.ndjson.gz"))->toBeFalse()
        ->and($disk->exists("audit-{$middle}.ndjson.gz"))->toBeTrue()
        ->and($disk->exists("audit-{$recent}.ndjson.gz"))->toBeTrue()
        ->and(AuditLog::where('action', 'audit_archive.pruned')->firstOrFail()->after['reason'])->toBe('disk');
});

test('the most recent copies are never deleted, and a disk that stays full is reported', function () {
    threeCopies();
    config(['security.audit_archive_retention_months' => 120, 'security.audit_archive_min_keep' => 3, 'security.audit_archive_disk_max_percent' => 85]);
    $this->partialMock(AuditArchive::class, fn ($mock) => $mock->shouldReceive('diskUsagePercent')->andReturn(95.0));

    $this->artisan('app:prune-audit-archive')->assertFailed();

    expect(AuditLog::where('action', 'audit_archive.pruned')->count())->toBe(0);
});
