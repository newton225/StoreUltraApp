<?php

namespace App\Support;

/**
 * What one kit costs and sells for. A null amount means a price is missing.
 */
final readonly class KitSummary
{
    public ?int $margin;

    /**
     * @param  array<int, int>  $missingPurchasePrices  Ids of the products with no price in effect.
     * @param  array<int, array{purchase: int, sale: int}>  $byShop  Amounts of one kit per shop id.
     */
    public function __construct(
        public ?int $purchase,
        public ?int $sale,
        public array $missingPurchasePrices,
        public array $byShop,
    ) {
        $this->margin = $purchase !== null && $sale !== null ? $sale - $purchase : null;
    }

    /**
     * Whether every price needed is known.
     */
    public function isComplete(): bool
    {
        return $this->purchase !== null && $this->sale !== null;
    }

    /**
     * The totals for a number of kits.
     *
     * @return array{purchase: int|null, sale: int|null, margin: int|null}
     */
    public function forKits(int $kits): array
    {
        return [
            'purchase' => $this->purchase !== null ? $this->purchase * $kits : null,
            'sale' => $this->sale !== null ? $this->sale * $kits : null,
            'margin' => $this->margin !== null ? $this->margin * $kits : null,
        ];
    }

    /**
     * @return array{purchase: int|null, sale: int|null, margin: int|null, complete: bool, missing_purchase_prices: array<int, int>}
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
