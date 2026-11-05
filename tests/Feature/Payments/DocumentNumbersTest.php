<?php

use App\Support\BusinessSettings;
use App\Support\DocumentNumbers;
use Illuminate\Support\Facades\DB;

test('numbers follow each other without a gap', function () {
    $numbers = app(DocumentNumbers::class);
    $year = now()->year;

    expect($numbers->next('receipt'))->toBe("REC-{$year}-0001");
    expect($numbers->next('receipt'))->toBe("REC-{$year}-0002");
    expect($numbers->next('receipt'))->toBe("REC-{$year}-0003");
});

test('the sequence starts again each year', function () {
    $numbers = app(DocumentNumbers::class);

    $this->travelTo('2026-12-31 23:00');
    expect($numbers->next('receipt'))->toBe('REC-2026-0001');
    expect($numbers->next('receipt'))->toBe('REC-2026-0002');

    $this->travelTo('2027-01-01 08:00');
    expect($numbers->next('receipt'))->toBe('REC-2027-0001');
});

test('the prefix follows the setting', function () {
    app(BusinessSettings::class)->set('document_prefix_receipt', 'RC');

    $this->travelTo('2026-07-15');

    expect(app(DocumentNumbers::class)->next('receipt'))->toBe('RC-2026-0001');
});

test('a number taken in a transaction that fails is given again', function () {
    $numbers = app(DocumentNumbers::class);
    $year = now()->year;

    try {
        DB::transaction(function () use ($numbers): void {
            $numbers->next('receipt');

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($numbers->next('receipt'))->toBe("REC-{$year}-0001");
});

test('an unknown document type is refused', function () {
    app(DocumentNumbers::class)->next('mystery');
})->throws(InvalidArgumentException::class);
