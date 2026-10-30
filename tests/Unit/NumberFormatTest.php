<?php

use App\Support\NumberFormat;
use App\Support\Settings;

// Expected values were checked against application/helpers/number_helper.php of InvoicePlane v1.7.2.

function numberFormat(array $overrides = []): NumberFormat
{
    return new NumberFormat(new Settings(array_merge([
        'currency_symbol' => '$',
        'currency_symbol_placement' => 'before',
        'thousands_separator' => ',',
        'decimal_point' => '.',
        'tax_rate_decimal_places' => '2',
        'default_item_decimals' => '2',
    ], $overrides)));
}

function europeanFormat(): NumberFormat
{
    return numberFormat([
        'currency_symbol' => '€',
        'currency_symbol_placement' => 'afterspace',
        'thousands_separator' => '.',
        'decimal_point' => ',',
    ]);
}

it('formats currency with the default settings', function () {
    expect(numberFormat()->currency(1234.5))->toBe('$1,234.50')
        ->and(numberFormat()->currency('1234.5'))->toBe('$1,234.50')
        ->and(numberFormat()->currency(null))->toBe('$0.00');
});

it('places the currency symbol after, with or without a space', function () {
    expect(numberFormat(['currency_symbol_placement' => 'after'])->currency(5))->toBe('5.00$')
        ->and(numberFormat(['currency_symbol_placement' => 'afterspace'])->currency(5))->toBe('5.00&nbsp;$');
});

it('uses 0 decimals when the decimal point is unset', function () {
    // number_helper.php:27: $decimals = $decimal_point ? tax_rate_decimal_places : 0
    expect(numberFormat(['decimal_point' => ''])->currency(1234.56))->toBe('$1,235');
});

it('formats european amounts and standardizes non-numeric currency input', function () {
    expect(europeanFormat()->currency(1234.5))->toBe('1.234,50&nbsp;€')
        ->and(europeanFormat()->currency('1.234,56'))->toBe('1.234,56&nbsp;€');
});

it('returns null from amount() for falsy values and formats the rest', function () {
    expect(numberFormat()->amount(0))->toBeNull()
        ->and(numberFormat()->amount(null))->toBeNull()
        ->and(numberFormat()->amount(''))->toBeNull()
        ->and(numberFormat()->amount('0'))->toBeNull()
        ->and(numberFormat()->amount('12'))->toBe('12.00')
        ->and(numberFormat()->amount(1234567.891))->toBe('1,234,567.89')
        // PHP truthiness: the string '0.00' is truthy, so legacy formats it.
        ->and(numberFormat()->amount('0.00'))->toBe('0.00');
});

it('formats quantities with default_item_decimals and null for falsy', function () {
    expect(numberFormat(['default_item_decimals' => '3'])->quantity('1.5'))->toBe('1.500')
        ->and(numberFormat()->quantity('2.00000000'))->toBe('2.00')
        ->and(numberFormat()->quantity(0))->toBeNull()
        ->and(numberFormat(['decimal_point' => ''])->quantity(7))->toBe('7');
});

it('standardizes european input', function () {
    expect(europeanFormat()->standardize('1.234,56'))->toBe('1234.56')
        ->and(europeanFormat()->standardize('12,5'))->toBe('12.5');
});

it('standardizes thousands-dot input with several dots and no comma', function () {
    // number_helper.php:91-93: the last dot becomes the decimal comma first.
    expect(europeanFormat()->standardize('1.234.567'))->toBe('1234.567');
});

it('passes numeric and falsy values through standardize unchanged', function () {
    expect(numberFormat()->standardize(12.5))->toBe(12.5)
        ->and(numberFormat()->standardize(7))->toBe(7)
        ->and(europeanFormat()->standardize('12.5'))->toBe('12.5')
        ->and(numberFormat()->standardize(''))->toBe('')
        ->and(numberFormat()->standardize(null))->toBeNull();
});

it('strips the thousands separator with the default settings', function () {
    expect(numberFormat()->standardize('1,234.56'))->toBe('1234.56');
});

it('only maps the decimal point when no thousands separator is set', function () {
    expect(numberFormat(['thousands_separator' => '', 'decimal_point' => ','])->standardize('12,5'))->toBe('12.5');
});
