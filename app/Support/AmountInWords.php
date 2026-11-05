<?php

namespace App\Support;

/**
 * An amount of money written out in words, for the line "Arrêtée la présente facture, la somme de : …" of an
 * invoice (French and English). The PHP "intl" extension is not required.
 */
class AmountInWords
{
    private const FR_UNITS = ['zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize'];

    private const FR_TENS = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];

    private const EN_UNITS = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    private const EN_TENS = [2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty', 6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety'];

    /**
     * "Sept cent quatre mille deux cent francs" / "Seven hundred four thousand two hundred francs".
     */
    public static function format(int $amount, string $locale = 'fr'): string
    {
        $amount = max(0, $amount);

        $words = $locale === 'en'
            ? self::english($amount).($amount === 1 ? ' franc' : ' francs')
            : self::french($amount).($amount > 1 ? ' francs' : ' franc');

        return mb_strtoupper(mb_substr($words, 0, 1)).mb_substr($words, 1);
    }

    /**
     * The number alone, in French.
     */
    public static function french(int $number): string
    {
        if ($number === 0) {
            return self::FR_UNITS[0];
        }

        $parts = [];

        foreach ([[1_000_000_000, 'milliard'], [1_000_000, 'million']] as [$scale, $name]) {
            $count = intdiv($number, $scale) % 1000;

            if ($count > 0) {
                $parts[] = self::frBelowThousand($count, false).' '.$name.($count > 1 ? 's' : '');
            }
        }

        $thousands = intdiv($number, 1000) % 1000;

        if ($thousands > 0) {
            $parts[] = $thousands === 1 ? 'mille' : self::frBelowThousand($thousands, false).' mille';
        }

        $rest = $number % 1000;

        if ($rest > 0) {
            $parts[] = self::frBelowThousand($rest, true);
        }

        return implode(' ', $parts);
    }

    /**
     * The number alone, in English.
     */
    public static function english(int $number): string
    {
        if ($number === 0) {
            return self::EN_UNITS[0];
        }

        $parts = [];

        foreach ([[1_000_000_000, 'billion'], [1_000_000, 'million'], [1_000, 'thousand']] as [$scale, $name]) {
            $count = intdiv($number, $scale) % 1000;

            if ($count > 0) {
                $parts[] = self::enBelowThousand($count).' '.$name;
            }
        }

        $rest = $number % 1000;

        if ($rest > 0) {
            $parts[] = self::enBelowThousand($rest);
        }

        return implode(' ', $parts);
    }

    /**
     * 1 to 999 in French. "cent" and "vingt" only take an "s" when nothing follows them (deux cents, quatre-vingts),
     * which is the case at the very end of the number, not before "mille" or "million".
     */
    private static function frBelowThousand(int $number, bool $final): string
    {
        $hundreds = intdiv($number, 100);
        $rest = $number % 100;

        if ($hundreds === 0) {
            return self::frBelowHundred($rest, $final);
        }

        $head = $hundreds === 1 ? 'cent' : (self::FR_UNITS[$hundreds] ?? (string) $hundreds).' cent';

        if ($rest === 0) {
            return $hundreds > 1 && $final ? $head.'s' : $head;
        }

        return $head.' '.self::frBelowHundred($rest, $final);
    }

    private static function frBelowHundred(int $number, bool $final): string
    {
        if ($number < 17) {
            return self::FR_UNITS[$number];
        }

        if ($number < 20) {
            return 'dix-'.self::FR_UNITS[$number - 10];
        }

        if ($number < 70) {
            $tens = intdiv($number, 10);
            $unit = $number % 10;

            return match (true) {
                $unit === 0 => self::FR_TENS[$tens],
                $unit === 1 => self::FR_TENS[$tens].' et un',
                default => self::FR_TENS[$tens].'-'.self::FR_UNITS[$unit],
            };
        }

        if ($number < 80) {
            return $number === 71 ? 'soixante et onze' : 'soixante-'.self::frBelowHundred($number - 60, false);
        }

        if ($number === 80) {
            return $final ? 'quatre-vingts' : 'quatre-vingt';
        }

        return 'quatre-vingt-'.self::frBelowHundred($number - 80, false);
    }

    private static function enBelowThousand(int $number): string
    {
        $hundreds = intdiv($number, 100);
        $rest = $number % 100;
        $words = [];

        if ($hundreds > 0) {
            $words[] = self::EN_UNITS[$hundreds].' hundred';
        }

        if ($rest > 0) {
            if ($rest < 20) {
                $words[] = self::EN_UNITS[$rest];
            } else {
                $unit = $rest % 10;
                $words[] = self::EN_TENS[intdiv($rest, 10)].($unit > 0 ? '-'.self::EN_UNITS[$unit] : '');
            }
        }

        return implode(' ', $words);
    }
}
