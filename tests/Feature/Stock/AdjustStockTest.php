<?php

use App\Actions\Stock\AdjustStock;
use App\Actions\Stock\RecordStockMovement;
use App\Enums\StockMovementType;
use App\Exceptions\InvalidStockMovement;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\StockLevels;

beforeEach(function () {
    $this->product = Product::factory()->create();
    $this->user = User::factory()->create();
    $this->adjust = app(AdjustStock::class);
});

test('units can be added by hand with a cost and a reason', function () {
    $movement = $this->adjust->handle($this->product, 12, 1500, 'Stock de départ', $this->user);

    expect($movement->type)->toBe(StockMovementType::Adjustment)
        ->and($movement->reason)->toBe('Stock de départ')
        ->and($movement->user_id)->toBe($this->user->id)
        ->and(app(StockLevels::class)->forProduct($this->product->id)['on_hand'])->toBe(12);
});

test('units removed by hand leave at the current average cost', function () {
    app(RecordStockMovement::class)->handle($this->product, StockMovementType::Receipt, 10, 1000);

    $movement = $this->adjust->handle($this->product, -4, null, 'Casse', $this->user);

    expect($movement->unit_cost)->toBe(1000)
        ->and(app(StockLevels::class)->forProduct($this->product->id)['on_hand'])->toBe(6);
});

test('a correction without reason, or adding units without a cost, is refused', function (int $quantity, int|float|null $cost, string $reason) {
    expect(fn () => $this->adjust->handle($this->product, $quantity, $cost, $reason, $this->user))
        ->toThrow(InvalidStockMovement::class);

    expect(StockMovement::count())->toBe(0);
})->with([
    'empty reason' => [5, 1000, ''],
    'blank reason' => [5, 1000, '   '],
    'no cost' => [5, null, 'Inventaire'],
    'zero cost' => [5, 0, 'Inventaire'],
]);

test('a correction can never make the stock negative', function () {
    app(RecordStockMovement::class)->handle($this->product, StockMovementType::Receipt, 3, 1000);

    expect(fn () => $this->adjust->handle($this->product, -4, null, 'Perte', $this->user))
        ->toThrow(InvalidStockMovement::class);

    expect(app(StockLevels::class)->forProduct($this->product->id)['on_hand'])->toBe(3);
});
