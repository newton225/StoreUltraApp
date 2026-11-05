<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Support\AuditArchive;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use RuntimeException;

class ArchiveAuditLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:archive-audit-log {--month= : Only this month (YYYY-MM); by default every finished month that has no copy yet}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Write a verifiable copy of each finished month of the audit journal outside the table (nothing is deleted)';

    /**
     * Execute the console command.
     */
    public function handle(AuditArchive $archive): int
    {
        $months = $this->months();
        $failed = false;

        foreach ($months as $month) {
            try {
                $result = $archive->archive($month);
            } catch (RuntimeException $exception) {
                $this->components->error($exception->getMessage());
                $failed = true;

                continue;
            }

            if ($result['status'] === 'created' && $result['manifest'] !== null) {
                $subject = new AuditLog;
                $subject->id = 0;
                AuditLog::record('audit_archive.created', $subject, [], [
                    'month' => $result['manifest']['month'],
                    'entries' => $result['manifest']['count'],
                    'file' => $result['manifest']['file'],
                    'file_sha256' => $result['manifest']['file_sha256'],
                ]);
            }

            $this->components->info("{$month->format('Y-m')}: {$result['status']}".($result['manifest'] !== null ? " ({$result['manifest']['count']} entries)" : ''));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function months(): array
    {
        if (is_string($this->option('month')) && $this->option('month') !== '') {
            return [CarbonImmutable::createFromFormat('!Y-m', (string) $this->option('month'))];
        }

        $first = AuditLog::query()->orderBy('id')->value('created_at');

        if ($first === null) {
            return [];
        }

        $months = [];
        $cursor = CarbonImmutable::parse($first)->startOfMonth();
        $limit = now()->toImmutable()->startOfMonth();

        while ($cursor->lessThan($limit)) {
            $months[] = $cursor;
            $cursor = $cursor->addMonth();
        }

        return $months;
    }
}
