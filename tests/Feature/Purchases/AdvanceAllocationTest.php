<?php

use App\Support\AdvanceAllocation;

test('the advance of the workbook is split between the two shops to the franc', function () {
    $shares = AdvanceAllocation::split(11200000, [1 => 14536200, 2 => 840000]);

    expect($shares)->toBe([1 => 10588145, 2 => 611855]);
});

test('the shares always add up to the total', function (int $total, array $weights) {
    expect(array_sum(AdvanceAllocation::split($total, $weights)))->toBe($total);
})->with([
    'three equal weights' => [100, ['a' => 1, 'b' => 1, 'c' => 1]],
    'uneven weights' => [999983, ['a' => 7, 'b' => 13, 'c' => 29, 'd' => 1]],
    'a small total' => [2, ['a' => 5, 'b' => 3, 'c' => 2]],
]);

test('equal weights share the leftover francs in the order given', function () {
    expect(AdvanceAllocation::split(100, ['a' => 1, 'b' => 1, 'c' => 1]))->toBe(['a' => 34, 'b' => 33, 'c' => 33]);
});

test('a shop with no purchase gets nothing', function () {
    expect(AdvanceAllocation::split(1000, ['a' => 0, 'b' => 500]))->toBe(['a' => 0, 'b' => 1000]);
});

test('nothing is allocated when there is no purchase at all', function () {
    expect(AdvanceAllocation::split(1000, ['a' => 0, 'b' => 0]))->toBe(['a' => 0, 'b' => 0]);
    expect(AdvanceAllocation::split(1000, []))->toBe([]);
});
