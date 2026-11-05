<?php

namespace App\Support;

class MoneyFormatter
{
    /**
     * An amount of FCFA written the way the language expects: 16 500 FCFA in french, 16,500 FCFA in english.
     */
    public static function format(int $amount, string $locale): string
    {
        $number = $locale === 'en'
            ? number_format($amount, 0, '.', ',')
            : number_format($amount, 0, ',', ' ');

        return $number.' FCFA';
    }
}
