<?php

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Exceptions\InvalidStockMovement;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only way stock changes. One movement is written to the ledger and the quantity on hand and weighted
 * average cost of the product are updated under a lock, in the same transaction, with an audit entry.
 * The stock never goes below zero.
 */
class RecordStockMovement
{
    /**
     * @param  int  $quantity  Signed: a receipt and a release are positive, an allocation negative, an adjustment either.
     * @param  int|float  $unitCost  Cost of one unit; it feeds the average cost when the stock grows.
     *
     * @throws InvalidStockMovement when the quantity is zero, has the wrong sign for the type, or would make the stock negative
     */
    public function handle(
        Product $product,
        StockMovementType $type,
        int $quantity,
        int|float $unitCost,
        ?User $user = null,
        ?string $reason = null,
        ?int $orderItemId = null,
        ?int $purchaseOrderItemId = null,
    ): StockMovement {
        $this->checkQuantity($type, $quantity);

        return DB::transaction(function () use ($product, $type, $quantity, $unitCost, $user, $reason, $orderItemId, $purchaseOrderItemId): StockMovement {
            DB::table('product_stocks')->insertOrIgnore([
                'product_id' => $product->id,
                'quantity_on_hand' => 0,
                'average_cost' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $stock = ProductStock::query()->where('product_id', $product->id)->lockForUpdate()->firstOrFail();
            $after = $stock->quantity_on_hand + $quantity;

            if ($after < 0) {
                throw new InvalidStockMovement('quantity', 'stock.errors.insufficient');
            }

            $cost = round((float) $unitCost, 2);
            $average = $quantity > 0
                ? round(($stock->quantity_on_hand * $stock->average_cost + $quantity * $cost) / $after, 2)
                : $stock->average_cost;

            $stock->forceFill(['quantity_on_hand' => $after, 'average_cost' => $average])->save();

            $movement = StockMovement::create([
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $cost,
                'order_item_id' => $orderItemId,
                'purchase_order_item_id' => $purchaseOrderItemId,
                'reason' => $reason,
                'user_id' => $user?->id,
            ]);

            AuditLog::record($type->auditAction(), $movement, [], [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_cost' => $cost,
                'on_hand' => $after,
                'reason' => $reason,
            ], $user);

            return $movement;
        });
    }

    private function checkQuantity(StockMovementType $type, int $quantity): void
    {
        $wrongSign = match ($type) {
            StockMovementType::Receipt, StockMovementType::Release => $quantity < 0,
            StockMovementType::Allocation => $quantity > 0,
            StockMovementType::Adjustment => false,
        };

        if ($quantity === 0 || $wrongSign) {
            throw new InvalidStockMovement('quantity', 'stock.errors.quantity');
        }
    }
}
