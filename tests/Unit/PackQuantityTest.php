<?php

use App\Support\PackQuantity;

test('what is needed is rounded up to whole packages', function () {
    expect(PackQuantity::units(99, 10))->toBe(100)
        ->and(PackQuantity::units(90, 10))->toBe(90)
        ->and(PackQuantity::units(1, 10))->toBe(10)
        ->and(PackQuantity::units(0, 10))->toBe(0)
        ->and(PackQuantity::units(-5, 10))->toBe(0)
        ->and(PackQuantity::units(5, 1))->toBe(5)
        ->and(PackQuantity::units(7, 0))->toBe(7)
        ->and(PackQuantity::packs(99, 10))->toBe(10)
        ->and(PackQuantity::packs(90, 10))->toBe(9)
        ->and(PackQuantity::packs(0, 10))->toBe(0);
});
