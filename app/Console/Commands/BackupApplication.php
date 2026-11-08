<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Support\Backup\Backup;
use Illuminate\Console\Command;
use RuntimeException;

class BackupApplication extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backup {--prune-only : Only delete the copies the retention rules no longer keep}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Back up the database and the private files in one encrypted archive, then apply the retention rules';

    /**
     * Execute the console command.
     */
    public function handle(Backup $backup): int
    {
        if (! $this->option('prune-only')) {
            try {
                $result = $backup->create();
            } catch (RuntimeException $exception) {
                $this->components->error($exception->getMessage());

                return self::FAILURE;
            }

            $subject = new AuditLog;
            $subject->id = 0;
            AuditLog::record('backup.created', $subject, [], [
                'file' => $result['file'],
                'size' => $result['size'],
                'file_sha256' => $result['sha256'],
                'database' => $result['database'],
                'files' => $result['files'],
            ]);

            $this->components->info("{$result['file']}: {$result['size']} bytes, {$result['files']} files, database {$result['database']}");
        }

        foreach ($backup->prune() as $deleted) {
            $this->components->info("Deleted by the retention rules: {$deleted}");
        }

        return self::SUCCESS;
    }
}
