<?php

namespace App\Support;

use App\Models\AuditLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * What the owner needs to know to trust the system is running: the scheduler, the queue, the backups, the audit
 * journal, the migrations, the disk, the mail and the production settings. Every check says ok, warning or error,
 * with a short detail key the screen turns into a sentence (the health lang files). Read only: it never changes
 * anything, and it never shows a secret.
 */
class HealthReport
{
    public const SCHEDULER_KEY = 'health.scheduler';

    public const AUDIT_KEY = 'health.audit_chain';

    /**
     * The checks, in the order they are shown.
     *
     * @return list<array{key: string, status: string, detail: string, params: array<string, string|int>, lines: list<string>}>
     */
    public function checks(): array
    {
        return [
            $this->backup(),
            $this->scheduler(),
            $this->queue(),
            $this->auditChain(),
            $this->migrations(),
            $this->configuration(),
            $this->disk(),
            $this->mail(),
        ];
    }

    /**
     * How many checks are in error and in warning.
     *
     * @param  list<array{status: string}>  $checks
     * @return array{errors: int, warnings: int}
     */
    public function summary(array $checks): array
    {
        $statuses = array_column($checks, 'status');

        return [
            'errors' => count(array_filter($statuses, fn (string $status): bool => $status === 'error')),
            'warnings' => count(array_filter($statuses, fn (string $status): bool => $status === 'warning')),
        ];
    }

    /**
     * The last backup (written by app:backup to the audit journal), and whether the backup is set up.
     *
     * @return array{key: string, status: string, detail: string, params: array<string, string|int>, lines: list<string>}
     */
    private function backup(): array
    {
        if ((string) config('backup.password') === '') {
            return $this->item('backup', 'error', 'not_configured');
        }

        $last = AuditLog::query()->where('action', 'backup.created')->latest('id')->first();

        if ($last === null) {
            return $this->item('backup', 'error', 'never');
        }

        $params = [
            'ago' => $this->ago($last->created_at),
            'size' => $this->megabytes((int) ($last->after['size'] ?? 0)),
        ];

        if ($last->created_at->diffInHours(now(), true) > 36) {
            return $this->item('backup', 'error', 'late', $params);
        }

        if (config('filesystems.disks.'.config('backup.disk').'.driver') === 'local') {
            return $this->item('backup', 'warning', 'local_disk', $params);
        }

        return $this->item('backup', 'ok', 'ok', $params);
    }

    /**
     * The scheduler leaves a heartbeat every minute: without it no daily task (backup, orders, journal checks) runs.
     *
     * @return array{key: string, status: string, detail: string, params: array<string, string|int>, lines: list<string>}
     */
    private function scheduler(): array
    {
        $beat = Cache::get(self::SCHEDULER_KEY);

        if (! is_int($beat)) {
            return $this->item('scheduler', 'error', 'never');
        }

        $minutes = (int) floor((now()->getTimestamp() - $beat) / 60);

        return $minutes > 10
            ? $this->item('scheduler', 'error', 'stopped', ['minutes' => $minutes])
            : $this->item('scheduler', 'ok', 'ok');
    }

    /**
     * Failed jobs and jobs that wait for too long.
     *
     * @return array{key: string, status: string, detail: string, params: array<string, string|int>, lines: list<string>}
     */
    private function queue(): array
    {
        if (config('queue.default') === 'sync') {
            return $this->item('queue', 'ok', 'sync');
        }

        try {
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            $oldest = Schema::hasTable('jobs') ? DB::table('jobs')->min('created_at') : null;
        } catch (Throwable) {
            return $this->item('queue', 'warning', 'unknown');
        }

        if ($failed > 0) {
            return $this->item('queue', 'warning', 'failed', ['count' => $failed]);
        }

        if ($oldest !== null && (now()->getTimestamp() - (int) $oldest) > 900) {
            return $this->item('queue', 'warning', 'stuck', ['minutes' => (int) floor((now()->getTimestamp() - (int) $oldest) / 60)]);
        }

        return $this->item('queue', 'ok', 'ok');
    }

    /**
     * The result of the daily verification of the audit journal chain.
     *
     * @return array{key: string, status: string, detail: string, params: array<string, string|int>, lines: list<string>}
     */
    private function auditChain(): array
    {
        $last = Cache::get(self::AUDIT_KEY);

        if (! is_array($last) || ! isset($last['at'], $last['ok'])) {
            return $this->item('audit_chain', 'warning', 'never');
        }

        $params = ['ago' => $this->ago(now()->setTimestamp((int) $last['at'])), 'entries' => (int) ($last['entries'] ?? 0)];

        if (! $last['ok']) {
            return $this->item('audit_chain', 'error', 'broken', $params);
        }

        return (now()->getTimestamp() - (int) $last['at']) > 36 * 3600
            ? $this->item('audit_chain', 'warning', 'late', $params)
            : $this->item('audit_chain', 'ok', 'ok', $params);
    }

    /**
     * Migrations that were deployed but not run.
     *
     * @return array{key: string, status: string, detail: string, params: array<string, string|int>, lines: list<string>}
     */
    private function migrations(): array
    {
        $pending = app(ProductionCheck::class)->pendingMigrations();

        return $pending === []
            ? $this->item('migrations', 'ok', 'ok')
            : $this->item('migrations', 'error', 'pending', [], $pending);
    }

    /**
     * The dangerous settings and the warnings of the production check.
     *
     * @return array{key: string, status: string, detail: string, params: array<string, string|int>, lines: list<string>}
     */
    private function configuration(): array
    {
        $check = app(ProductionCheck::class);
        $result = $check->run();
        $role = $check->databaseRole();
        $errors = [...$result['errors'], ...$role['errors']];
        $warnings = [...$result['warnings'], ...$role['warnings']];

        return match (true) {
            $errors !== [] => $this->item('configuration', 'error', 'errors', ['count' => count($errors)], [...$errors, ...$warnings]),
            $warnings !== [] => $this->item('configuration', 'warning', 'warnings', ['count' => count($warnings)], $warnings),
            default => $this->item('configuration', 'ok', 'ok'),
        };
    }

    /**
     * Free space where the files and the backups are written.
     *
     * @return array{key: string, status: string, detail: string, params: array<string, string|int>, lines: list<string>}
     */
    private function disk(): array
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        if ($free === false || $total === false || $total <= 0) {
            return $this->item('disk', 'warning', 'unknown');
        }

        $percent = (int) round($free / $total * 100);
        $params = ['percent' => $percent, 'free' => $this->megabytes((int) $free)];

        return match (true) {
            $percent < 10 => $this->item('disk', 'error', 'full', $params),
            $percent < 20 => $this->item('disk', 'warning', 'low', $params),
            default => $this->item('disk', 'ok', 'ok', $params),
        };
    }

    /**
     * Whether the application can really send mail (password reset links, alerts).
     *
     * @return array{key: string, status: string, detail: string, params: array<string, string|int>, lines: list<string>}
     */
    private function mail(): array
    {
        return in_array(config('mail.default'), ['log', 'array'], true)
            ? $this->item('mail', 'warning', 'not_sending')
            : $this->item('mail', 'ok', 'ok');
    }

    /**
     * @param  array<string, string|int>  $params
     * @param  list<string>  $lines
     * @return array{key: string, status: string, detail: string, params: array<string, string|int>, lines: list<string>}
     */
    private function item(string $key, string $status, string $detail, array $params = [], array $lines = []): array
    {
        return ['key' => $key, 'status' => $status, 'detail' => $detail, 'params' => $params, 'lines' => $lines];
    }

    private function ago(CarbonInterface $date): string
    {
        return $date->diffForHumans();
    }

    private function megabytes(int $bytes): string
    {
        return number_format($bytes / 1048576, 1, app()->getLocale() === 'fr' ? ',' : '.', ' ').' '.__('health.unit_mb');
    }
}
