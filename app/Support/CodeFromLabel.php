<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Str;

/**
 * The internal code of a reference item (payment method, kind of cost, kind of document), made from its
 * French name so nobody has to type it: lower case, digits and underscores, made unique with a number.
 */
class CodeFromLabel
{
    /**
     * @param  Closure(string): bool  $taken  whether a code is already used
     */
    public static function make(string $label, Closure $taken, int $max = 30): string
    {
        $base = Str::of(Str::slug($label, '_'))->limit($max - 3, '')->toString();
        $base = $base === '' ? 'item' : $base;

        $code = $base;
        $suffix = 2;

        while ($taken($code)) {
            $code = $base.'_'.$suffix++;
        }

        return $code;
    }
}
