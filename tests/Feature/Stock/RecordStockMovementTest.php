<?php

use App\Actions\Stock\RecordStockMovement;
use App\Enums\StockMovementType;
use App\Exceptions\InvalidStockMovement;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\StockLevels;

beforeEach(function () {
    $this->product = Product::factory()->create();
    $this->record = app(RecordStockMovement::class);
});

function levels(Product $product): array
{
    return app(StockLevels::class)->forProduct($product->id);
}

test('a receipt on an empty stock sets the quantity and the cost', function () {
    $this->record->handle($this->product, StockMovementType::Receipt, 10, 1000);

    expect(levels($this->product))->toBe(['on_hand' => 10, 'average_cost' => 1000, 'value' => 10000]);
});

test('a second receipt at another cost gives the weighted average cost', function () {
    $this->record->handle($this->product, StockMovementType::Receipt, 10, 1000);
    $this->record->handle($this->product, StockMovementType::Receipt, 10, 1200);

    expect(levels($this->product))->toBe(['on_hand' => 20, 'average_cost' => 1100, 'value' => 22000]);
});

test('an allocation shrinks the stock and leaves the average cost alone', function () {
    $this->record->handle($this->product, StockMovementType::Receipt, 10, 1000);
    $this->record->handle($this->product, StockMovementType::Receipt, 10, 1200);
    $this->record->handle($this->product, StockMovementType::Allocation, -5, 1100);

    expect(levels($this->product))->toBe(['on_hand' => 15, 'average_cost' => 1100, 'value' => 16500]);
});

test('a release gives units back at the cost they left with', function () {
    $this->record->handle($this->product, StockMovementType::Receipt, 10, 1000);
    $this->record->handle($this->product, StockMovementType::Allocation, -10, 1000);
    $this->record->handle($this->product, StockMovementType::Receipt, 10, 2000);
    $this->record->handle($this->product, StockMovementType::Release, 10, 1000);

    expect(levels($this->product)['on_hand'])->toBe(20)
        ->and(levels($this->product)['average_cost'])->toBe(1500);
});

test('a withdrawal above the stock is refused and writes nothing', function () {
    $this->record->handle($this->product, StockMovementType::Receipt, 15, 1000);

    expect(fn () => $this->record->handle($this->product, StockMovementType::Allocation, -16, 1000))
        ->toThrow(InvalidStockMovement::class);

    expect(levels($this->product)['on_hand'])->toBe(15)
        ->and(StockMovement::count())->toBe(1);
});

test('a zero quantity and a wrong sign for the type are refused', function (StockMovementType $type, int $quantity) {
    $this->record->handle($this->product, StockMovementType::Receipt, 10, 1000);

    expect(fn () => $this->record->handle($this->product, $type, $quantity, 1000))
        ->toThrow(InvalidStockMovement::class);

    expect(StockMovement::count())->toBe(1);
})->with([
    'zero receipt' => [StockMovementType::Receipt, 0],
    'negative receipt' => [StockMovementType::Receipt, -1],
    'positive allocation' => [StockMovementType::Allocation, 1],
    'negative release' => [StockMovementType::Release, -1],
    'zero adjustment' => [StockMovementType::Adjustment, 0],
]);

test('a movement is never modified or removed', function () {
    $movement = $this->record->handle($this->product, StockMovementType::Receipt, 10, 1000);

    $movement->update(['quantity' => 99]);
    $movement->delete();

    expect(StockMovement::sole()->quantity)->toBe(10);
});

test('every movement writes an audit entry named after its type', function (StockMovementType $type, int $quantity, string $action) {
    $this->record->handle($this->product, StockMovementType::Receipt, 10, 1000);
    $this->record->handle($this->product, $type, $quantity, 1000, reason: 'Test');

    expect(AuditLog::where('action', $action)->count())->toBeGreaterThanOrEqual(1);
})->with([
    'receipt' => [StockMovementType::Receipt, 5, 'stock.received'],
    'allocation' => [StockMovementType::Allocation, -5, 'stock.allocated'],
    'release' => [StockMovementType::Release, 5, 'stock.released'],
    'adjustment' => [StockMovementType::Adjustment, -5, 'stock.adjusted'],
]);

test('a product with no movement has an empty stock', function () {
    expect(levels($this->product))->toBe(['on_hand' => 0, 'average_cost' => 0, 'value' => 0]);
});
