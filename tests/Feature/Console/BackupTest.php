<?php

use App\Models\AuditLog;
use App\Support\Backup\Backup;
use App\Support\Backup\BackupRetention;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * A backup whose database dump is a small stand-in file: the real dump needs pg_dump or a file database, which a
 * test inside a transaction cannot use. Everything else (archive, encryption, disk, retention) is real.
 */
function fakeBackup(): void
{
    Storage::fake('backup-test');
    Storage::fake('local');
    config(['backup.disk' => 'backup-test', 'backup.password' => 'a-long-test-password']);

    app()->bind(Backup::class, fn () => new class extends Backup
    {
        protected function dumpDatabase(string $work): string
        {
            $path = $work.DIRECTORY_SEPARATOR.'database.dump';
            file_put_contents($path, 'DUMP-CONTENT');

            return $path;
        }
    });
}

test('a backup is one encrypted archive holding the database, the private files and a manifest', function () {
    fakeBackup();
    Storage::disk('local')->put('attachments/proof.pdf', 'PDF-CONTENT');

    $this->artisan('app:backup')->assertSuccessful();

    $files = Storage::disk('backup-test')->files();
    expect($files)->toHaveCount(1)->and($files[0])->toStartWith('storeultra-')->toEndWith('.zip');

    $zip = new ZipArchive;
    $zip->open(Storage::disk('backup-test')->path($files[0]));

    // Without the password nothing can be read.
    expect($zip->getFromName('manifest.json'))->toBeFalse();

    $zip->setPassword('a-long-test-password');
    $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);

    expect($zip->getFromName('database.dump'))->toBe('DUMP-CONTENT')
        ->and($zip->getFromName('files/attachments/proof.pdf'))->toBe('PDF-CONTENT')
        ->and($manifest['files'])->toBe(1)
        ->and($manifest['sha256']['database.dump'])->toBe(hash('sha256', 'DUMP-CONTENT'));
});

test('the backup refuses to run without a password and leaves a trace of the one that ran', function () {
    fakeBackup();
    config(['backup.password' => '']);

    $this->artisan('app:backup')->assertFailed();
    expect(Storage::disk('backup-test')->files())->toBe([])
        ->and(AuditLog::query()->where('action', 'backup.created')->count())->toBe(0);

    config(['backup.password' => 'a-long-test-password']);
    $this->artisan('app:backup')->assertSuccessful();

    expect(AuditLog::query()->where('action', 'backup.created')->count())->toBe(1);
});

test('the retention keeps every recent copy, then one a day, a week and a month, and nothing past the maximum', function () {
    $now = CarbonImmutable::parse('2026-11-08 12:00:00');
    $rules = ['all_days' => 7, 'daily_days' => 30, 'weekly_days' => 90, 'max_days' => 365];
    $copies = [
        'today-1' => $now->subHours(1),
        'today-2' => $now->subHours(5),
        'day10-a' => $now->subDays(10)->setTime(1, 0),
        'day10-b' => $now->subDays(10)->setTime(22, 0),
        'week-old-a' => CarbonImmutable::parse('2026-09-07 01:00:00'),
        'week-old-b' => CarbonImmutable::parse('2026-09-08 01:00:00'),
        'month-old' => CarbonImmutable::parse('2026-03-02 01:00:00'),
        'too-old' => $now->subDays(400),
    ];

    $delete = (new BackupRetention($rules))->toDelete($copies, $now);

    expect($delete)->toContain('day10-a', 'week-old-a', 'too-old')
        ->and($delete)->not->toContain('today-1', 'today-2', 'day10-b', 'week-old-b', 'month-old');
});

test('the prune-only option deletes the old copies and makes no new one', function () {
    fakeBackup();
    config(['backup.retention.max_days' => 365]);
    Storage::disk('backup-test')->put('storeultra-20200101-010000.zip', 'old');
    Storage::disk('backup-test')->put('other-file.txt', 'not a backup');

    $this->artisan('app:backup --prune-only')->assertSuccessful();

    expect(Storage::disk('backup-test')->exists('storeultra-20200101-010000.zip'))->toBeFalse()
        ->and(Storage::disk('backup-test')->exists('other-file.txt'))->toBeTrue()
        ->and(Storage::disk('backup-test')->files())->toHaveCount(1);
});
