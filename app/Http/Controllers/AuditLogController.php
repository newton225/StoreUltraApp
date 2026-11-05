<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\AuditArchive;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    private const PER_PAGE = 50;

    /**
     * Read the journal, newest entries first.
     */
    public function index(AuditArchive $archive): Response
    {
        $entries = AuditLog::query()
            ->with('user:id,name')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->through(fn (AuditLog $entry): array => [
                'id' => $entry->id,
                'action' => $entry->action,
                'user' => $entry->user?->name,
                'ip' => $entry->ip_address,
                'subject' => class_basename($entry->subject_type).' #'.$entry->subject_id,
                'subject_kind' => class_basename($entry->subject_type),
                'subject_id' => $entry->subject_id,
                'before' => $entry->before,
                'after' => $entry->after,
                'at' => $entry->created_at?->toIso8601String(),
            ]);

        return Inertia::render('audit/index', [
            'entries' => $entries,
            'archives' => array_reverse(array_map(fn (array $manifest): array => [
                'month' => $manifest['month'],
                'count' => $manifest['count'],
                'created_at' => $manifest['created_at'],
                'file_sha256' => $manifest['file_sha256'],
                'purged_at' => $manifest['purged_at'] ?? null,
            ], $archive->manifests())),
        ]);
    }

    /**
     * Download the copy of a month. Each download is written to the journal.
     */
    public function download(string $month, AuditArchive $archive): StreamedResponse
    {
        $manifest = collect($archive->manifests())->firstWhere('month', $month);

        abort_if($manifest === null || isset($manifest['purged_at']), 404);

        $subject = new AuditLog;
        $subject->id = 0;
        AuditLog::record('audit_archive.downloaded', $subject, [], ['month' => $month, 'file' => $manifest['file']]);

        return $archive->disk()->download($manifest['file']);
    }
}
