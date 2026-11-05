<?php

use Illuminate\Support\Arr;

/**
 * @return array<string, array<int, string>>
 */
function translationKeysByGroup(string $locale): array
{
    $keys = [];

    foreach (glob(lang_path($locale.'/*.php')) as $file) {
        $keys[basename($file, '.php')] = array_keys(Arr::dot(require $file));
    }

    return $keys;
}

test('every supported language has the same groups as the reference language', function () {
    $reference = array_keys(translationKeysByGroup('en'));

    foreach (config('app.supported_locales') as $locale) {
        expect(array_keys(translationKeysByGroup($locale)))->toEqualCanonicalizing($reference);
    }
});

test('every supported language has the same keys as the reference language', function () {
    $reference = translationKeysByGroup('en');

    foreach (config('app.supported_locales') as $locale) {
        foreach (translationKeysByGroup($locale) as $group => $keys) {
            expect($keys)->toEqualCanonicalizing($reference[$group], "{$locale}/{$group}");
        }
    }
});

test('a missing key renders the key itself', function () {
    expect(__('common.this_key_does_not_exist'))->toBe('common.this_key_does_not_exist');
});
