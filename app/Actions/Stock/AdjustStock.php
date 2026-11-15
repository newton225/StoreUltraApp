<?php

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Exceptions\InvalidStockMovement;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\StockLevels;

/**
 * A manual correction of the stock (breakage, loss, inventory, opening stock). The reason is always asked.
 * Units that come in need a cost; units that go out leave at the current average cost.
 */
class AdjustStock
{
    public function __construct(private RecordStockMovement $record, private StockLevels $levels) {}

    /**
     * @param  int  $quantity  Signed: positive adds units, negative removes them.
     * @param  int|float|null  $unitCost  Required (above zero) when units come in, ignored when they go out.
     *
     * @throws InvalidStockMovement when the reason or the cost is missing, or the stock would go below zero
     */
    public function handle(Product $product, int $quantity, int|float|null $unitCost, string $reason, User $user): StockMovement
    {
        if (trim($reason) === '') {
            throw new InvalidStockMovement('reason', 'stock.errors.reason_required');
        }

        if ($quantity > 0 && ($unitCost === null || $unitCost <= 0)) {
            throw new InvalidStockMovement('unit_cost', 'stock.errors.cost_required');
        }

        $cost = $quantity > 0 ? $unitCost : $this->levels->forProduct($product->id)['average_cost'];

        return $this->record->handle($product, StockMovementType::Adjustment, $quantity, $cost ?? 0, $user, trim($reason));
    }
}
