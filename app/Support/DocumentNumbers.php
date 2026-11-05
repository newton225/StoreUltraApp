<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DocumentNumbers
{
    public function __construct(private BusinessSettings $settings) {}

    /**
     * The next number of a document type, such as REC-2026-0001. The sequence is gapless and starts
     * again each year; the prefix is a setting. A number taken inside a transaction that fails is
     * given again.
     */
    public function next(string $type, ?CarbonInterface $at = null): string
    {
        $prefix = $this->settings->get("document_prefix_{$type}");
        $year = ($at ?? now())->year;

        $number = DB::transaction(function () use ($type, $year): int {
            DB::table('document_sequences')->insertOrIgnore([
                'document_type' => $type,
                'year' => $year,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('document_sequences')
                ->where('document_type', $type)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $next = $row->last_number + 1;

            DB::table('document_sequences')
                ->where('id', $row->id)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return $next;
        });

        return sprintf('%s-%d-%04d', $prefix, $year, $number);
    }
}
