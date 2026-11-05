<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Support\AuditArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneAuditArchive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prune-audit-archive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete the oldest copies of the audit journal when they are too old or the disk is too full (the manifests stay)';

    /**
     * Execute the console command.
     */
    public function handle(AuditArchive $archive): int
    {
        $months = max(1, (int) config('security.audit_archive_retention_months'));
        $keep = max(1, (int) config('security.audit_archive_min_keep'));
        $maxPercent = (int) config('security.audit_archive_disk_max_percent');
        $limit = now()->startOfMonth()->subMonths($months)->format('Y-m');

        $present = collect($archive->manifests())->reject(fn (array $manifest): bool => isset($manifest['purged_at']))->pluck('month')->values();
        $protected = $present->take(-$keep)->all();
        $deleted = 0;

        foreach ($present as $month) {
            if (in_array($month, $protected, true)) {
                continue;
            }

            if ($month < $limit) {
                $this->delete($archive, $month, 'age');
                $deleted++;
            }
        }

        $present = $present->reject(fn (string $month): bool => $month < $limit && ! in_array($month, $protected, true))->values();

        foreach ($present as $month) {
            $usage = $archive->diskUsagePercent();

            if ($maxPercent <= 0 || $usage === null || $usage <= $maxPercent) {
                break;
            }

            if (in_array($month, $protected, true)) {
                break;
            }

            $this->delete($archive, $month, 'disk');
            $deleted++;
        }

        $usage = $archive->diskUsagePercent();

        if ($maxPercent > 0 && $usage !== null && $usage > $maxPercent) {
            Log::critical('The disk of the audit journal copies is still too full.', ['usage_percent' => $usage, 'limit_percent' => $maxPercent]);
            $this->components->error("The disk is {$usage}% full (limit {$maxPercent}%) and no more copy can be deleted: free space or enlarge the disk.");

            return self::FAILURE;
        }

        $this->components->info("Copies of the audit journal: {$deleted} deleted.");

        return self::SUCCESS;
    }

    private function delete(AuditArchive $archive, string $month, string $reason): void
    {
        $manifest = collect($archive->manifests())->firstWhere('month', $month);
        $freed = $archive->purge($month, $reason);

        $subject = new AuditLog;
        $subject->id = 0;
        AuditLog::record('audit_archive.pruned', $subject, [], [
            'month' => $month,
            'reason' => $reason,
            'bytes_freed' => $freed,
            'file_sha256' => $manifest['file_sha256'] ?? null,
        ]);

        $this->components->info("Copy {$month} deleted ({$reason}).");
    }
}
