<?php

namespace App\Support\Exports;

use App\Models\User;
use App\Support\BusinessSettings;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * A PDF of the table, in landscape when it has many columns, with the company name as watermark (like the
 * other documents). dompdf keeps the whole page in memory, so a very long list is cut at MAX_ROWS and the
 * page says so: the spreadsheet formats hold everything.
 */
class PdfFormat
{
    public const MAX_ROWS = 2000;

    public function __construct(private readonly BusinessSettings $settings) {}

    public function render(ExportTable $table, User $user): string
    {
        $total = count($table->rows);
        $truncated = $total > self::MAX_ROWS;
        $shown = $truncated ? new ExportTable($table->title, $table->columns, array_slice($table->rows, 0, self::MAX_ROWS)) : $table;

        return Pdf::loadView('exports.table', [
            'table' => $shown,
            'total' => $total,
            'truncated' => $truncated,
            'locale' => app()->getLocale(),
            'companyName' => (string) $this->settings->get('company_name'),
            'generatedAt' => now()->format('d/m/Y H:i'),
            'generatedBy' => $user->name,
        ])->setPaper('a4', count($table->columns) > 6 ? 'landscape' : 'portrait')->output();
    }
}
