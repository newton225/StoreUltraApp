<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class InitialPaymentMethodsSeeder extends Seeder
{
    /**
     * The usual ways to pay: code, french label, english label, whether a reference is needed.
     *
     * @var array<int, array{string, string, string, bool}>
     */
    private const METHODS = [
        ['cash', 'Espèces', 'Cash', false],
        ['wave', 'Wave', 'Wave', true],
        ['orange_money', 'Orange Money', 'Orange Money', true],
        ['moov_money', 'Moov Money', 'Moov Money', true],
        ['mtn_money', 'MTN Mobile Money', 'MTN Mobile Money', true],
        ['transfer', 'Virement', 'Bank transfer', true],
    ];

    /**
     * Seed the payment methods. Running it again changes nothing.
     */
    public function run(): void
    {
        foreach (self::METHODS as [$code, $labelFr, $labelEn, $requiresReference]) {
            PaymentMethod::firstOrCreate(
                ['code' => $code],
                ['label_fr' => $labelFr, 'label_en' => $labelEn, 'requires_reference' => $requiresReference],
            );
        }
    }
}
