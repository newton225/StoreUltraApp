<?php

namespace App\Support\Backup;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * One backup: the database and the private files in a single archive, encrypted with a password and written to
 * the backup disk. Restoring is done by hand (see the README), never from a button.
 */
class Backup
{
    private const PREFIX = 'storeultra-';

    /**
     * Make a backup now.
     *
     * @return array{file: string, size: int, sha256: string, files: int, database: string}
     */
    public function create(?CarbonImmutable $now = null): array
    {
        $password = (string) config('backup.password');

        if ($password === '') {
            throw new RuntimeException('BACKUP_PASSWORD is not set: the archive must be encrypted, nothing was done.');
        }

        $now ??= CarbonImmutable::now();
        $work = sys_get_temp_dir().DIRECTORY_SEPARATOR.'storeultra-backup-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($work);

        try {
            $dump = $this->dumpDatabase($work);
            $archive = $work.DIRECTORY_SEPARATOR.'archive.zip';
            $files = $this->buildArchive($archive, $dump, $password, $now);
            $name = self::PREFIX.$now->format('Ymd-His').'.zip';
            $sha256 = (string) hash_file('sha256', $archive);
            $disk = Storage::disk((string) config('backup.disk'));

            $stream = fopen($archive, 'rb');

            if ($stream === false) {
                throw new RuntimeException('The archive that was built could not be read.');
            }

            $disk->writeStream($name, $stream);
            fclose($stream);

            $size = (int) filesize($archive);

            if ($disk->size($name) !== $size) {
                throw new RuntimeException('The archive written to the backup disk does not have the size of the one that was built.');
            }

            return ['file' => $name, 'size' => $size, 'sha256' => $sha256, 'files' => $files, 'database' => DB::connection()->getDriverName()];
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * Delete the copies the retention rules no longer keep.
     *
     * @return list<string>
     */
    public function prune(?CarbonImmutable $now = null): array
    {
        $disk = Storage::disk((string) config('backup.disk'));
        $copies = [];

        foreach ($disk->files() as $path) {
            if (preg_match('/^'.self::PREFIX.'(\d{8})-(\d{6})\.zip$/', basename($path), $found) === 1) {
                $date = CarbonImmutable::createFromFormat('Ymd-His', $found[1].'-'.$found[2]);

                if ($date !== null) {
                    $copies[$path] = $date;
                }
            }
        }

        /** @var array{all_days: int, daily_days: int, weekly_days: int, max_days: int} $rules */
        $rules = config('backup.retention');
        $delete = (new BackupRetention($rules))->toDelete($copies, $now ?? CarbonImmutable::now());

        foreach ($delete as $path) {
            $disk->delete($path);
        }

        return $delete;
    }

    /**
     * Write the database to a file in the working folder: a PostgreSQL custom-format dump, or a consistent copy of
     * the SQLite file. Returns the path.
     */
    protected function dumpDatabase(string $work): string
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $path = $work.DIRECTORY_SEPARATOR.'database.sqlite';
            $connection->getPdo()->exec("VACUUM INTO '".str_replace("'", "''", $path)."'");

            return $path;
        }

        if ($driver !== 'pgsql') {
            throw new RuntimeException("The backup does not know the database driver \"{$driver}\".");
        }

        $username = (string) config('backup.database.username');

        if ($username === '') {
            throw new RuntimeException('BACKUP_DB_USERNAME is not set: the backup uses a read-only role of its own, never the one of the application.');
        }

        $binary = (string) config('backup.database.pg_dump');
        $this->assertDumpClientIsRecentEnough($binary);

        $path = $work.DIRECTORY_SEPARATOR.'database.dump';
        $result = Process::env(['PGPASSWORD' => (string) config('backup.database.password')])->timeout(3600)->run([
            $binary, '--format=custom', '--no-owner', '--file='.$path,
            '--host='.config('database.connections.pgsql.host'), '--port='.config('database.connections.pgsql.port'),
            '--username='.$username, (string) config('database.connections.pgsql.database'),
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('pg_dump failed: '.trim($result->errorOutput()));
        }

        return $path;
    }

    /**
     * The dump client has to be at least as recent as the database server, or it refuses or dumps wrongly.
     */
    private function assertDumpClientIsRecentEnough(string $binary): void
    {
        $output = Process::run([$binary, '--version']);

        if (! $output->successful() || preg_match('/(\d+)(?:\.\d+)*/', $output->output(), $found) !== 1) {
            throw new RuntimeException("The dump client \"{$binary}\" could not be run: set BACKUP_PG_DUMP.");
        }

        $server = intdiv((int) (DB::selectOne('show server_version_num')->server_version_num ?? 0), 10000);

        if ((int) $found[1] < $server) {
            throw new RuntimeException("{$binary} is version {$found[1]}, older than the database server ({$server}): install a client at least as recent and set BACKUP_PG_DUMP.");
        }
    }

    /**
     * Build the encrypted archive: the dump, every private file, and a manifest. Returns the number of files.
     */
    private function buildArchive(string $archive, string $dump, string $password, CarbonImmutable $now): int
    {
        $zip = new ZipArchive;

        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The archive could not be created.');
        }

        $zip->setPassword($password);
        $entries = [];

        $add = function (string $path, string $name) use ($zip, &$entries): void {
            $zip->addFile($path, $name);
            $zip->setEncryptionName($name, ZipArchive::EM_AES_256);
            $entries[$name] = (string) hash_file('sha256', $path);
        };

        $add($dump, basename($dump));

        $root = rtrim(Storage::disk('local')->path(''), '/\\');
        $skip = $this->backupFolderInside($root);
        $count = 0;

        if (is_dir($root)) {
            foreach (File::allFiles($root, true) as $file) {
                $relative = str_replace('\\', '/', $file->getRelativePathname());

                if ($skip !== null && str_starts_with($relative, $skip.'/')) {
                    continue;
                }

                $add($file->getPathname(), 'files/'.$relative);
                $count++;
            }
        }

        $manifest = json_encode([
            'application' => config('app.name'),
            'created_at' => $now->toIso8601String(),
            'database' => basename($dump),
            'files' => $count,
            'sha256' => $entries,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $zip->addFromString('manifest.json', (string) $manifest);
        $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256);

        if (! $zip->close()) {
            throw new RuntimeException('The archive could not be written.');
        }

        return $count;
    }

    /**
     * The folder of the backup disk when it sits inside the private files, so a backup never contains the backups.
     */
    private function backupFolderInside(string $root): ?string
    {
        $diskRoot = config('filesystems.disks.'.config('backup.disk').'.root');

        if (! is_string($diskRoot)) {
            return null;
        }

        $diskRoot = str_replace('\\', '/', $diskRoot);
        $root = str_replace('\\', '/', $root);

        return str_starts_with($diskRoot, $root.'/') ? substr($diskRoot, strlen($root) + 1) : null;
    }
}
