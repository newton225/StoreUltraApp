<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\Exports\CsvFormat;
use App\Support\Exports\ListExports;
use App\Support\Exports\PdfFormat;
use App\Support\Exports\XlsxFormat;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ListExportController extends Controller
{
    private const FORMATS = [
        'csv' => ['text/csv; charset=UTF-8', 'csv'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
        'pdf' => ['application/pdf', 'pdf'],
    ];

    /**
     * Download a list as CSV, Excel or PDF, with the filters of the page it comes from. The user must be
     * allowed to open that page, and the gesture is written to the audit journal.
     */
    public function show(Request $request, string $view, string $format, ListExports $exports, CsvFormat $csv, XlsxFormat $xlsx, PdfFormat $pdf): Response
    {
        $user = $request->user();

        abort_unless($user !== null && isset(self::FORMATS[$format]) && in_array($view, ListExports::VIEWS, true) && ListExports::allows($user, $view), 404);

        $table = $exports->build($view, $request, $user);

        $content = match ($format) {
            'csv' => $csv->render($table),
            'xlsx' => $xlsx->render($table),
            default => $pdf->render($table, $user),
        };

        AuditLog::record('export.generated', $user, [], [
            'view' => $view,
            'format' => $format,
            'rows' => count($table->rows),
            'filters' => $request->query(),
        ], $user);

        [$mime, $extension] = self::FORMATS[$format];

        return response($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$view.'-'.today()->toDateString().'.'.$extension.'"',
        ]);
    }
}
