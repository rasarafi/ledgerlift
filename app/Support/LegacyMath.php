<?php

namespace App\Support;

/**
 * round() and number_format() as legacy's PHP computes them. Legacy runs php:8.2, whose round()
 * (which number_format() calls) first pre-rounds the value to 15 significant digits, so
 * 168446.28749999998 counts as 168446.2875 and rounds up to 168446.288. PHP 8.4 dropped the
 * pre-rounding and gives 168446.287. Pre-rounding with sprintf('%.15g') first reproduces 8.2.
 * Evidence (scripts/rounding-sweep.php): of 200,000 seeded values of total / 100 * pct at 2 or
 * 3 dp, 8.2 and 8.4 differ on 104 for each function, and this emulation matches 8.2 on all.
 *
 * Domain: money values, that is decimals of at most 15 significant digits with |v| >= 10^-places.
 * PHP 8.2 chose its pre-rounding precision from the value's magnitude and the places; outside
 * this domain (17-digit values, or values below the last place) the two can differ.
 * number_format() also clamps negative decimals to 0, as PHP 8.2 did (8.3 rounds left of the point).
 */
final class LegacyMath
{
    public static function round(float $value, int $places): float
    {
        return round(self::preRound($value), $places);
    }

    public static function numberFormat(float $value, int $decimals, string $decimalPoint = '.', string $thousandsSeparator = ','): string
    {
        return number_format(self::preRound($value), max(0, $decimals), $decimalPoint, $thousandsSeparator);
    }

    private static function preRound(float $value): float
    {
        // '%.15g' prints INF and NAN as text that would read back as 0.
        return is_finite($value) ? (float) sprintf('%.15g', $value) : $value;
    }
}
