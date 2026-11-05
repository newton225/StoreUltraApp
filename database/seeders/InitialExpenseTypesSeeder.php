<?php

namespace Database\Seeders;

use App\Models\ExpenseType;
use Illuminate\Database\Seeder;

class InitialExpenseTypesSeeder extends Seeder
{
    /**
     * The usual logistics costs: code, french label, english label.
     *
     * @var array<int, array{string, string, string}>
     */
    private const TYPES = [
        ['transport', 'Transport', 'Transport'],
        ['handling', 'Manutention', 'Handling'],
        ['contingency', 'Imprévu', 'Contingency'],
        ['other', 'Autre', 'Other'],
    ];

    /**
     * Seed the cost types. Running it again changes nothing.
     */
    public function run(): void
    {
        foreach (self::TYPES as [$code, $labelFr, $labelEn]) {
            ExpenseType::firstOrCreate(['code' => $code], ['label_fr' => $labelFr, 'label_en' => $labelEn]);
        }
    }
}
