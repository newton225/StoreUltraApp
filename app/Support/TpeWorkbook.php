<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Reads the workbook of small businesses (TPE) of the project: one sheet per sub-prefecture, one block of
 * rows per TPE (its manager first, then its assistants). The reader finds the columns by their header, so
 * the sheets may order them differently, and it uses only the zip and XML extensions of PHP.
 *
 * @phpstan-type Person array{name: string, role: string, trade: string|null, phone: string|null}
 * @phpstan-type Tpe array{sheet: string, zone: string, department: string|null, village: string|null, name: string, people: list<Person>}
 */
class TpeWorkbook
{
    /**
     * @return list<Tpe>
     */
    public function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Not a valid xlsx file: {$path}");
        }

        try {
            $strings = $this->sharedStrings($zip);
            $tpes = [];

            foreach ($this->sheets($zip) as $sheetName => $entry) {
                $xml = $zip->getFromName($entry);

                if ($xml !== false) {
                    array_push($tpes, ...$this->readSheet($sheetName, $this->rows($xml, $strings)));
                }
            }

            return $tpes;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<string, string> sheet name => path of its XML inside the archive
     */
    private function sheets(ZipArchive $zip): array
    {
        $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $relations = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));

        if ($workbook === false || $relations === false) {
            throw new RuntimeException('The workbook structure cannot be read.');
        }

        $targets = [];

        foreach ($relations->Relationship as $relation) {
            $targets[(string) $relation['Id']] = 'xl/'.ltrim((string) $relation['Target'], '/');
        }

        $sheets = [];

        foreach ($workbook->sheets->sheet as $sheet) {
            $id = (string) $sheet->attributes('r', true)['id'];

            if (isset($targets[$id])) {
                $sheets[(string) $sheet['name']] = str_replace('xl/xl/', 'xl/', $targets[$id]);
            }
        }

        return $sheets;
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $document = simplexml_load_string($xml);
        $strings = [];

        foreach ($document === false ? [] : $document->si as $item) {
            $text = '';

            foreach ($item->xpath('.//*[local-name()="t"]') ?: [] as $part) {
                $text .= (string) $part;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @param  list<string>  $strings
     * @return list<array<string, string>> rows as column letter => trimmed text
     */
    private function rows(string $xml, array $strings): array
    {
        $sheet = simplexml_load_string($xml);
        $rows = [];

        foreach ($sheet === false ? [] : $sheet->sheetData->row as $row) {
            $cells = [];

            foreach ($row->c as $cell) {
                $column = preg_replace('/\d+/', '', (string) $cell['r']) ?? '';
                $raw = isset($cell->v) ? (string) $cell->v : '';
                $text = (string) $cell['t'] === 's' ? ($strings[(int) $raw] ?? '') : $raw;
                $text = trim((string) preg_replace('/\s+/u', ' ', $text));

                if ($text !== '') {
                    $cells[$column] = $text;
                }
            }

            $rows[] = $cells;
        }

        return $rows;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<Tpe>
     */
    private function readSheet(string $sheetName, array $rows): array
    {
        $headerIndex = null;
        $columns = [];

        foreach ($rows as $index => $cells) {
            $found = array_flip(array_map(fn (string $text): string => $this->key($text), $cells));

            if (isset($found['nom et prenoms'])) {
                $headerIndex = $index;
                $columns = $found;

                break;
            }
        }

        if ($headerIndex === null) {
            return [];
        }

        $title = implode(' ', array_map(fn (array $cells): string => implode(' ', $cells), array_slice($rows, 0, $headerIndex)));
        $department = preg_match('/DEPARTEMENT DE\s+([^\/]+?)\s*(?:\/|$)/iu', $title, $match) === 1 ? $this->title($match[1]) : null;
        $titleZone = preg_match('/\bSP\s+([\p{L}\' -]+)/iu', $title, $match) === 1 ? $this->title($match[1]) : null;

        $zoneColumn = $columns['sous-prefecture'] ?? null;
        $villageColumn = $columns['village / localite'] ?? $columns['village'] ?? null;
        $fallbackZone = $titleZone ?? $this->title((string) preg_replace('/^TPE\s+/i', '', $sheetName));

        $heads = [];
        $people = [];
        $zone = $fallbackZone;
        $village = null;
        $current = null;

        foreach (array_slice($rows, $headerIndex + 1) as $cells) {
            $first = $cells[array_key_first($cells) ?? ''] ?? '';

            if ($cells === [] || str_starts_with($this->key($first), 's/total')) {
                continue;
            }

            $name = $cells[$columns['nom et prenoms']] ?? '';
            $tpeName = $cells[$columns['nom de la tpe'] ?? ''] ?? '';

            if ($zoneColumn !== null && isset($cells[$zoneColumn])) {
                $zone = $this->title($cells[$zoneColumn]);
            }

            if ($name === '') {
                continue;
            }

            if ($tpeName !== '') {
                if ($villageColumn !== null && isset($cells[$villageColumn])) {
                    $village = $cells[$villageColumn];
                }

                $current = count($heads);
                $heads[] = [
                    'sheet' => $sheetName,
                    'zone' => $zone,
                    'department' => $department,
                    'village' => $village,
                    'name' => $tpeName,
                ];
                $people[$current] = [];
            }

            if ($current === null) {
                continue;
            }

            $people[$current][] = [
                'name' => $name,
                'role' => $this->key($cells[$columns['role'] ?? ''] ?? '') === 'responsable' ? 'manager' : 'assistant',
                'trade' => $cells[$columns['metier'] ?? ''] ?? null,
                'phone' => $cells[$columns['contact'] ?? ''] ?? null,
            ];
        }

        return array_map(fn (array $head, int $index): array => [...$head, 'people' => $people[$index]], $heads, array_keys($heads));
    }

    /**
     * A header or label compared without case, accents or extra marks ("Rôle" and "role" are the same).
     */
    private function key(string $text): string
    {
        $plain = strtr(mb_strtolower($text), [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'n°' => 'n',
        ]);

        return trim($plain);
    }

    private function title(string $text): string
    {
        return mb_convert_case(mb_strtolower(trim($text)), MB_CASE_TITLE);
    }
}
