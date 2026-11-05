<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Http\Requests\DownloadDocumentArchiveRequest;
use App\Models\AuditLog;
use App\Models\Zone;
use App\Support\OrderDocumentArchive;
use App\Support\WeekCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderDocumentArchiveController extends Controller
{
    /**
     * Download, in one zip, the proformas or the invoices already issued for chosen orders, or for the orders of a
     * zone (sub-prefecture) and a week. Nothing is issued here, and every document packed is written to the audit log.
     */
    public function show(DownloadDocumentArchiveRequest $request, OrderDocumentArchive $archive, WeekCalendar $calendar): BinaryFileResponse|RedirectResponse
    {
        $type = DocumentType::from($request->validated('type'));
        $user = $request->user();
        $zone = null;
        $week = null;

        if ($request->filled('zone')) {
            $week = $calendar->startOf(CarbonImmutable::parse($request->validated('week')));
            $zoneId = $request->integer('zone');
            $documents = $archive->forZone($zoneId, $week, $type, $user);
            $zone = (string) Zone::query()->whereKey($zoneId)->value('name');
        } else {
            $documents = $archive->forOrders(array_map('intval', (array) $request->validated('orders')), $type, $user);
        }

        if ($documents->isEmpty()) {
            return $this->refuse('orders.archive.none');
        }

        if ($documents->count() > OrderDocumentArchive::MAX_FILES) {
            return $this->refuse('orders.archive.too_many');
        }

        $path = $archive->pack($documents);

        foreach ($documents as $document) {
            AuditLog::record('document.downloaded', $document, [], ['number' => $document->number, 'zip' => true], $user);
        }

        return response()
            ->download($path, $archive->archiveName($type, $zone, $week), ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    private function refuse(string $messageKey): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => __($messageKey)]);

        return back();
    }
}
