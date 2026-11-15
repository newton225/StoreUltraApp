<?php

namespace App\Support;

class MoneyFormatter
{
    /**
     * An amount of FCFA written the way the language expects: 16 500 FCFA in french, 16,500 FCFA in english.
     * A unit price keeps its decimal part (12,5 FCFA); a whole amount shows none.
     */
    public static function format(int|float $amount, string $locale): string
    {
        $decimals = floor($amount) === (float) $amount ? 0 : 2;

        $number = $locale === 'en'
            ? number_format($amount, $decimals, '.', ',')
            : number_format($amount, $decimals, ',', ' ');

        if ($decimals === 2) {
            $number = rtrim(rtrim($number, '0'), $locale === 'en' ? '.' : ',');
        }

        return $number.' FCFA';
    }
}
