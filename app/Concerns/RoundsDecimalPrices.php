<?php

namespace App\Concerns;

/**
 * Lets a price be typed with a decimal part (12,5 or 12.5): money is stored in
 * whole FCFA, so the value is rounded to the nearest FCFA before validation.
 * Anything that is not a number is left alone and fails the `integer` rule.
 */
trait RoundsDecimalPrices
{
    protected function prepareForValidation(): void
    {
        $rounded = [];

        foreach (['purchase_price', 'sale_price'] as $field) {
            $value = $this->input($field);

            if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
                continue;
            }

            $normalised = str_replace([' ', ','], ['', '.'], (string) $value);

            if (is_numeric($normalised)) {
                $rounded[$field] = (int) round((float) $normalised);
            }
        }

        $this->merge($rounded);
    }
}
