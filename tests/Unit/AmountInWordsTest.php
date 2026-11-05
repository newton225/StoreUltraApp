<?php

use App\Support\AmountInWords;

test('an amount is written in French the way an invoice needs it', function (int $amount, string $words) {
    expect(AmountInWords::format($amount))->toBe($words);
})->with([
    'the amount of the owner\'s model, with the plural a final "cents" takes' => [704_200, 'Sept cent quatre mille deux cents francs'],
    'zero' => [0, 'Zéro franc'],
    'one franc' => [1, 'Un franc'],
    'seventy-one' => [71, 'Soixante et onze francs'],
    'seventy-seven' => [77, 'Soixante-dix-sept francs'],
    'eighty takes an s at the end' => [80, 'Quatre-vingts francs'],
    'eighty before a thousand does not' => [80_000, 'Quatre-vingt mille francs'],
    'ninety-one' => [91, 'Quatre-vingt-onze francs'],
    'two hundred at the end takes an s' => [200, 'Deux cents francs'],
    'two hundred before a thousand does not' => [200_000, 'Deux cent mille francs'],
    'one hundred and one' => [101, 'Cent un francs'],
    'one thousand' => [1_000, 'Mille francs'],
    'twenty-one thousand' => [21_000, 'Vingt et un mille francs'],
    'a million' => [1_000_000, 'Un million francs'],
    'two millions' => [2_340_500, 'Deux millions trois cent quarante mille cinq cents francs'],
    'the sum of the sample lines' => [740_200, 'Sept cent quarante mille deux cents francs'],
    'a negative amount counts as zero' => [-5, 'Zéro franc'],
]);

test('an amount is written in English too', function (int $amount, string $words) {
    expect(AmountInWords::format($amount, 'en'))->toBe($words);
})->with([
    [704_200, 'Seven hundred four thousand two hundred francs'],
    [0, 'Zero francs'],
    [1, 'One franc'],
    [21_015, 'Twenty-one thousand fifteen francs'],
    [1_000_000, 'One million francs'],
]);
