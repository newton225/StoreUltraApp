<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A unit price kept exactly as typed: a whole number of FCFA stays an int, a price with a decimal part (12.5) a float.
 *
 * @implements CastsAttributes<int|float, int|float|string>
 */
class Amount implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): int|float|null
    {
        if ($value === null) {
            return null;
        }

        $number = round((float) $value, 2);

        return floor($number) === $number ? (int) $number : $number;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): int|float|string|null
    {
        return $value;
    }
}
