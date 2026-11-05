<?php

namespace App\Support\Exports;

/**
 * What an export holds, whatever its format: a title, the column labels and the rows. Amounts and counts
 * stay plain numbers (a spreadsheet can add them up); everything else is text.
 */
final readonly class ExportTable
{
    /**
     * @param  list<string>  $columns
     * @param  list<list<int|float|string|null>>  $rows
     */
    public function __construct(
        public string $title,
        public array $columns,
        public array $rows,
    ) {}
}
