<?php

namespace App\Support;

use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Monthly copies of the audit journal kept outside the table: one compressed file of lines (JSON, one per
 * entry, with its hash) and one manifest per month (count, first and last entry, the hash the first entry
 * follows, fingerprints of the content and of the file). A copy is never overwritten, and `verify()` checks
 * the copies against themselves (the chain recomputed from the lines) and against the journal.
 *
 * @phpstan-type Manifest array{month: string, count: int, first_id: int, last_id: int, previous_hash: string|null, first_hash: string, last_hash: string, content_sha256: string, file_sha256: string, file: string, created_at: string, purged_at?: string, purged_reason?: string}
 */
class AuditArchive
{
    public function disk(): Filesystem
    {
        return Storage::disk((string) config('security.audit_archive_disk'));
    }

    /**
     * Write the copy of a finished month. Returns what happened: `created`, `exists` (the same copy is already
     * there) or `empty` (nothing was written that month). A copy that differs from what the journal holds now
     * is an error: it is never replaced.
     *
     * @return array{status: 'created'|'exists'|'empty', manifest: Manifest|null}
     */
    public function archive(CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();

        if ($start->addMonth()->greaterThan(now())) {
            throw new RuntimeException("The month {$start->format('Y-m')} is not over yet.");
        }

        $entries = AuditLog::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $start->addMonth())
            ->orderBy('id')
            ->get();

        if ($entries->isEmpty()) {
            return ['status' => 'empty', 'manifest' => null];
        }

        $lines = $entries->map(fn (AuditLog $entry): string => (string) json_encode([
            'id' => $entry->id,
            'user_id' => $entry->user_id,
            'action' => $entry->action,
            'subject_type' => $entry->subject_type,
            'subject_id' => $entry->subject_id,
            'before' => $entry->before,
            'after' => $entry->after,
            'ip_address' => $entry->ip_address,
            'user_agent' => $entry->user_agent,
            'hash' => $entry->hash,
            'created_at' => $entry->created_at?->format('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $content = $lines->implode("\n")."\n";
        $contentHash = hash('sha256', $content);
        $name = 'audit-'.$start->format('Y-m');

        if ($this->disk()->exists("{$name}.manifest.json")) {
            $existing = $this->manifest("{$name}.manifest.json");

            if ($existing['content_sha256'] !== $contentHash) {
                throw new RuntimeException("The copy of {$start->format('Y-m')} already exists and differs from the journal: it was not replaced.");
            }

            return ['status' => 'exists', 'manifest' => $existing];
        }

        $compressed = (string) gzencode($content, 9);
        $first = $entries->first();
        $last = $entries->last();

        /** @var Manifest $manifest */
        $manifest = [
            'month' => $start->format('Y-m'),
            'count' => $entries->count(),
            'first_id' => (int) $first->id,
            'last_id' => (int) $last->id,
            'previous_hash' => AuditLog::query()->where('id', '<', $first->id)->orderByDesc('id')->value('hash'),
            'first_hash' => (string) $first->hash,
            'last_hash' => (string) $last->hash,
            'content_sha256' => $contentHash,
            'file_sha256' => hash('sha256', $compressed),
            'file' => "{$name}.ndjson.gz",
            'created_at' => now()->toDateTimeString(),
        ];

        $this->disk()->put("{$name}.ndjson.gz", $compressed);
        $this->disk()->put("{$name}.manifest.json", (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return ['status' => 'created', 'manifest' => $manifest];
    }

    /**
     * The manifests of every copy kept, oldest month first.
     *
     * @return list<Manifest>
     */
    public function manifests(): array
    {
        $files = collect($this->disk()->files())
            ->filter(fn (string $file): bool => str_ends_with($file, '.manifest.json'))
            ->sort()
            ->values();

        return array_values($files->map(fn (string $file): array => $this->manifest($file))->all());
    }

    /**
     * Check every copy: the file is the one the manifest describes, the chain recomputed from its lines
     * holds, and the lines agree with the entries still in the journal. Returns the problems found.
     *
     * @return list<string>
     */
    public function verify(): array
    {
        $problems = [];
        $previousMonthLast = null;

        foreach ($this->manifests() as $manifest) {
            $month = $manifest['month'];

            if ($previousMonthLast !== null && $manifest['previous_hash'] !== $previousMonthLast) {
                $problems[] = "{$month}: this copy does not follow the previous month's copy (a month is missing or was changed).";
            }

            $previousMonthLast = $manifest['last_hash'];

            if (isset($manifest['purged_at'])) {
                if ($this->disk()->exists($manifest['file'])) {
                    $problems[] = "{$month}: the manifest says the copy was deleted, but its file is still there.";
                }

                continue;
            }

            $raw = $this->disk()->get($manifest['file']);

            if ($raw === null || hash('sha256', $raw) !== $manifest['file_sha256']) {
                $problems[] = "{$month}: the file of the copy is missing or was changed.";

                continue;
            }

            $content = (string) gzdecode($raw);

            if (hash('sha256', $content) !== $manifest['content_sha256']) {
                $problems[] = "{$month}: the content of the copy does not match its manifest.";

                continue;
            }

            $lines = array_map(fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR), array_filter(explode("\n", $content)));
            $previous = $manifest['previous_hash'];

            foreach ($lines as $line) {
                $expected = AuditChain::hash($previous, $line['user_id'], $line['action'], $line['subject_type'], (int) $line['subject_id'], $line['before'], $line['after'], $line['created_at']);

                if ($expected !== $line['hash']) {
                    $problems[] = "{$month}: entry #{$line['id']} of the copy does not match its fingerprint.";
                }

                $live = AuditLog::query()->whereKey($line['id'])->value('hash');

                if ($live !== null && $live !== $line['hash']) {
                    $problems[] = "{$month}: entry #{$line['id']} differs between the copy and the journal.";
                }

                $previous = $line['hash'];
            }

            if (count($lines) !== $manifest['count'] || $previous !== $manifest['last_hash']) {
                $problems[] = "{$month}: the number of entries or the last fingerprint does not match the manifest.";
            }
        }

        return $problems;
    }

    /**
     * Delete the file of a copy to free space. The small manifest stays, marked as deleted, so the months
     * keep chaining and the deletion stays provable. Returns the bytes freed.
     */
    public function purge(string $month, string $reason): int
    {
        $manifestFile = "audit-{$month}.manifest.json";
        $manifest = $this->manifest($manifestFile);

        if (isset($manifest['purged_at'])) {
            return 0;
        }

        $size = (int) $this->disk()->size($manifest['file']);
        $this->disk()->delete($manifest['file']);

        $manifest['purged_at'] = now()->toDateTimeString();
        $manifest['purged_reason'] = $reason;
        $this->disk()->put($manifestFile, (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $size;
    }

    /**
     * How full the disk holding the copies is, in percent; null when it cannot be known (a remote store).
     */
    public function diskUsagePercent(): ?float
    {
        $root = config('filesystems.disks.'.config('security.audit_archive_disk').'.root');

        if (! is_string($root) || ! is_dir($root)) {
            return null;
        }

        $total = disk_total_space($root);
        $free = disk_free_space($root);

        return $total === false || $free === false || $total <= 0 ? null : round(($total - $free) / $total * 100, 1);
    }

    /**
     * @return Manifest
     */
    private function manifest(string $file): array
    {
        /** @var Manifest */
        return json_decode((string) $this->disk()->get($file), true, 512, JSON_THROW_ON_ERROR);
    }
}
