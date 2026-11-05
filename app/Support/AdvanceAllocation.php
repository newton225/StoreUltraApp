<?php

namespace App\Support;

class AdvanceAllocation
{
    /**
     * Split a whole amount between shops in proportion to their weights, to the franc, with the
     * largest remainders getting the leftover francs. The shares always add up to the total
     * (unless every weight is zero, in which case nothing is allocated).
     *
     * @param  int  $total  The amount to split.
     * @param  array<int|string, int>  $weights  The purchase of each shop, by key.
     * @return array<int|string, int>
     */
    public static function split(int $total, array $weights): array
    {
        $weightSum = array_sum($weights);

        if ($weightSum <= 0) {
            return array_map(fn (): int => 0, $weights);
        }

        $shares = [];
        $remainders = [];

        foreach ($weights as $key => $weight) {
            $exact = $total * $weight;
            $shares[$key] = intdiv($exact, $weightSum);
            $remainders[$key] = $exact % $weightSum;
        }

        $leftover = $total - array_sum($shares);
        $order = array_keys($weights);
        $position = array_flip($order);

        usort($order, fn ($a, $b): int => [$remainders[$b], $position[$a]] <=> [$remainders[$a], $position[$b]]);

        foreach (array_slice($order, 0, $leftover) as $key) {
            $shares[$key]++;
        }

        return $shares;
    }
}
