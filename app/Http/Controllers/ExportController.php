<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CsvReports;
use App\Support\ReportFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * The lists and reports that can be downloaded as CSV.
     *
     * @var list<string>
     */
    public const REPORTS = ['orders', 'clients', 'payments', 'arrears', 'expenses', 'shops', 'profits'];

    /**
     * Whether the profile may download a report. Field agents only get what concerns their sub-prefectures.
     */
    public static function allows(User $user, string $report): bool
    {
        return match ($report) {
            'orders', 'clients' => true,
            'profits' => Gate::forUser($user)->allows('view-profit'),
            'payments', 'arrears', 'shops' => Gate::forUser($user)->allows('manage-orders'),
            'expenses' => Gate::forUser($user)->allows('manage-expenses'),
            default => false,
        };
    }

    /**
     * Download a list or report as a CSV that opens correctly in Excel (UTF-8, semicolons).
     */
    public function show(Request $request, string $report, CsvReports $reports): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user !== null && in_array($report, self::REPORTS, true) && self::allows($user, $report), 404);

        $filters = ReportFilters::fromRequest($request);
        ['headers' => $headers, 'rows' => $rows] = $reports->build($report, $user, $filters);

        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';', '"', '');

            foreach ($rows as $row) {
                fputcsv($out, array_map(self::safe(...), $row), ';', '"', '');
            }

            fclose($out);
        }, 'storeultra-'.$report.'-'.$filters['from'].'-'.$filters['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * A text starting like a formula would be run by a spreadsheet: it is made harmless.
     */
    private static function safe(int|float|string|null $value): int|float|string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value) && $value !== '' && str_contains('=+-@', $value[0])) {
            return "'".$value;
        }

        return $value;
    }
}
