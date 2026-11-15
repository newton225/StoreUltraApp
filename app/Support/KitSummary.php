<?php

namespace App\Support;

/**
 * What one kit costs and sells for. A null amount means a price is missing.
 * The amount of one kit may carry a decimal part (unit prices are kept as typed);
 * the totals for a number of kits are whole FCFA.
 */
final readonly class KitSummary
{
    public int|float|null $margin;

    /**
     * @param  array<int, int>  $missingPurchasePrices  Ids of the products with no price in effect.
     * @param  array<int, array{purchase: int|float, sale: int|float}>  $byShop  Amounts of one kit per shop id.
     */
    public function __construct(
        public int|float|null $purchase,
        public int|float|null $sale,
        public array $missingPurchasePrices,
        public array $byShop,
    ) {
        $this->margin = $purchase !== null && $sale !== null ? self::tidy($sale - $purchase) : null;
    }

    /**
     * An amount without floating point noise: a whole number stays an int, else two decimals at most.
     */
    public static function tidy(int|float $amount): int|float
    {
        $rounded = round($amount, 2);

        return floor($rounded) === $rounded ? (int) $rounded : $rounded;
    }

    /**
     * Whether every price needed is known.
     */
    public function isComplete(): bool
    {
        return $this->purchase !== null && $this->sale !== null;
    }

    /**
     * The totals for a number of kits, rounded to the FCFA.
     *
     * @return array{purchase: int|null, sale: int|null, margin: int|null}
     */
    public function forKits(int $kits): array
    {
        $purchase = $this->purchase !== null ? (int) round($this->purchase * $kits) : null;
        $sale = $this->sale !== null ? (int) round($this->sale * $kits) : null;

        return [
            'purchase' => $purchase,
            'sale' => $sale,
            'margin' => $purchase !== null && $sale !== null ? $sale - $purchase : null,
        ];
    }

    /**
     * @return array{purchase: int|float|null, sale: int|float|null, margin: int|float|null, complete: bool, missing_purchase_prices: array<int, int>}
     */
    public function toArray(): array
    {
        return [
            'purchase' => $this->purchase,
            'sale' => $this->sale,
            'margin' => $this->margin,
            'complete' => $this->isComplete(),
            'missing_purchase_prices' => $this->missingPurchasePrices,
        ];
    }
}
