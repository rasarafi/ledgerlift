<?php

use App\Support\LegacyMath;

/*
 * Legacy runs PHP 8.2, whose round() and number_format() pre-round to 15 significant digits;
 * PHP 8.4 does not. The values below are from a 200,000-value fuzz of v = total / 100 * pct in
 * the legacy container, where they are among the ~0.05% that 8.2 and 8.4 round differently.
 */

/** value => [places, what PHP 8.2 gives] */
const LEGACY_MATH_SPLITS = [
    [168446.28749999998, 3, '168446.288'],
    [130107.66649999999, 3, '130107.667'],
    [106081.52849999999, 3, '106081.529'],
    [157417.71349999998, 3, '157417.714'],
    [32396.581499999997, 3, '32396.582'],
];

it('rounds as PHP 8.2 does where PHP 8.4 differs', function (float $value, int $places, string $legacy) {
    expect(LegacyMath::round($value, $places))->toBe((float) $legacy);
})->with(LEGACY_MATH_SPLITS);

it('formats as PHP 8.2 does where PHP 8.4 differs', function (float $value, int $places, string $legacy) {
    expect(LegacyMath::numberFormat($value, $places, '.', ''))->toBe($legacy);
})->with(LEGACY_MATH_SPLITS);

it('rounds and formats ordinary values as round() and number_format() do', function () {
    expect(LegacyMath::round(2.375, 2))->toBe(2.38)
        ->and(LegacyMath::round(190.422, 2))->toBe(190.42)
        ->and(LegacyMath::round(-1.005, 2))->toBe(-1.01)
        ->and(LegacyMath::round(0.0, 2))->toBe(0.0)
        ->and(LegacyMath::numberFormat(1234567.891, 2))->toBe('1,234,567.89')
        ->and(LegacyMath::numberFormat(1234.5, 2, ',', '.'))->toBe('1.234,50')
        ->and(LegacyMath::numberFormat(12.0, 0))->toBe('12')
        ->and(LegacyMath::numberFormat(1713.8000000000002, 2, '.', ''))->toBe('1713.80');
});

it('exists because plain round() on this PHP gives another cent than legacy', function () {
    [$value, $places, $legacy] = LEGACY_MATH_SPLITS[0];

    expect(round($value, $places))->not->toBe((float) $legacy)
        ->and(number_format($value, $places, '.', ''))->not->toBe($legacy);
})->skip(PHP_VERSION_ID < 80400, 'PHP < 8.4 pre-rounds like legacy.');
