<?php

namespace App\Support\Exports;

use RuntimeException;
use ZipArchive;

/**
 * A plain .xlsx written with the zip and XML extensions of PHP, with no dependency: one sheet, a bold frozen
 * header row, column widths that fit the content, whole numbers as numbers (with thousands separators) and
 * every other value as inline text. Text is never stored as a formula, so a value such as "=1+1" stays text.
 */
class XlsxFormat
{
    public function render(ExportTable $table): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');

        if ($path === false) {
            throw new RuntimeException('Cannot create a temporary file.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot write the spreadsheet.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$this->escape($this->sheetName($table->title)).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($table));
        $zip->close();

        $content = (string) file_get_contents($path);
        unlink($path);

        return $content;
    }

    private function sheet(ExportTable $table): string
    {
        $widths = array_map(fn (string $column): int => mb_strlen($column), $table->columns);
        $rows = $this->row(1, $table->columns, header: true);

        foreach ($table->rows as $index => $row) {
            foreach ($row as $column => $value) {
                $widths[$column] = max($widths[$column] ?? 0, mb_strlen((string) $value));
            }

            $rows .= $this->row($index + 2, $row);
        }

        $cols = '';

        foreach ($widths as $index => $width) {
            $cols .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.min(60, max(8, $width + 2)).'" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>'.$cols.'</cols><sheetData>'.$rows.'</sheetData></worksheet>';
    }

    /**
     * @param  list<int|float|string|null>  $cells
     */
    private function row(int $number, array $cells, bool $header = false): string
    {
        $xml = '<row r="'.$number.'">';

        foreach ($cells as $index => $value) {
            $ref = $this->columnLetter($index).$number;

            if ($value === null || $value === '') {
                continue;
            }

            if (! $header && (is_int($value) || is_float($value))) {
                $xml .= '<c r="'.$ref.'" s="2"><v>'.$value.'</v></c>';
            } else {
                $xml .= '<c r="'.$ref.'" t="inlineStr"'.($header ? ' s="1"' : '').'><is><t xml:space="preserve">'.$this->escape((string) $value).'</t></is></c>';
            }
        }

        return $xml.'</row>';
    }

    private function columnLetter(int $index): string
    {
        $letters = '';

        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letters = chr(65 + (($n - 1) % 26)).$letters;
        }

        return $letters;
    }

    private function escape(string $text): string
    {
        $clean = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text);

        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function sheetName(string $title): string
    {
        $name = trim((string) preg_replace('/[\[\]:*?\/\\\\]/', ' ', $title));

        return mb_substr($name === '' ? 'Export' : $name, 0, 31);
    }
}
