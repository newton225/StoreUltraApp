<?php

namespace App\Concerns;

/**
 * Lets a price be typed with a decimal comma or point (12,5 or 12.5): the comma becomes a point
 * and the value is kept as typed. Anything that is not a number is left alone and fails validation.
 */
trait NormalizesDecimalPrices
{
    /**
     * @param  array<int, string>  $fields
     */
    protected function normalizePriceFields(array $fields): void
    {
        $normalised = [];

        foreach ($fields as $field) {
            $value = $this->normalizedPrice($this->input($field));

            if ($value !== null) {
                $normalised[$field] = $value;
            }
        }

        $this->merge($normalised);
    }

    protected function normalizedPrice(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $text = str_replace([' ', ','], ['', '.'], (string) $value);

        return is_numeric($text) ? $text : null;
    }
}
