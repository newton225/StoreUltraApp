<?php

namespace App\Support\Exports;

/**
 * A CSV that opens correctly in Excel: UTF-8 with a byte order mark, semicolon separators, and any text
 * that looks like a formula made harmless.
 */
class CsvFormat
{
    public function render(ExportTable $table): string
    {
        $out = fopen('php://temp', 'w+');

        if ($out === false) {
            return '';
        }

        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_map(self::safe(...), $table->columns), ';', '"', '');

        foreach ($table->rows as $row) {
            fputcsv($out, array_map(self::safe(...), $row), ';', '"', '');
        }

        rewind($out);
        $content = (string) stream_get_contents($out);
        fclose($out);

        return $content;
    }

    /**
     * A text starting like a formula would be run by a spreadsheet: it is made harmless.
     */
    public static function safe(int|float|string|null $value): int|float|string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value) && $value !== '' && str_contains("=+-@\t\r", $value[0])) {
            return "'".$value;
        }

        return $value;
    }
}
