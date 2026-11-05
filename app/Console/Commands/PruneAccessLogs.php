<?php

namespace App\Console\Commands;

use App\Models\AccessLog;
use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAccessLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prune-access-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete the access journal lines older than the retention period, and write the purge to the audit journal';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = max(1, (int) config('security.access_log_retention_days'));
        $limit = now()->subDays($days);

        $deleted = AccessLog::query()->where('created_at', '<', $limit)->delete();

        $subject = new AccessLog;
        $subject->id = 0;
        AuditLog::record('access_log.pruned', $subject, [], ['older_than' => $limit->toDateTimeString(), 'retention_days' => $days, 'deleted' => $deleted]);

        $this->components->info("Access journal: {$deleted} line(s) older than {$days} days deleted.");

        return self::SUCCESS;
    }
}
