<?php

namespace App\Support;

/**
 * round() and number_format() as legacy's PHP computes them. Legacy runs php:8.2, whose round()
 * (which number_format() calls) first pre-rounds the value to 15 significant digits, so
 * 168446.28749999998 counts as 168446.2875 and rounds up to 168446.288. PHP 8.4 dropped the
 * pre-rounding and gives 168446.287. Pre-rounding with sprintf('%.15g') first reproduces 8.2.
 * Evidence: a 200,000-value fuzz of total / 100 * pct at 2 and 3 dp found 104 values where
 * 8.2 and 8.4 differ for each function, and this emulation matched 8.2 on all 200,000.
 */
final class LegacyMath
{
    public static function round(float $value, int $places): float
    {
        return round(self::preRound($value), $places);
    }

    public static function numberFormat(float $value, int $decimals, string $decimalPoint = '.', string $thousandsSeparator = ','): string
    {
        return number_format(self::preRound($value), $decimals, $decimalPoint, $thousandsSeparator);
    }

    private static function preRound(float $value): float
    {
        // '%.15g' prints INF and NAN as text that would read back as 0.
        return is_finite($value) ? (float) sprintf('%.15g', $value) : $value;
    }
}
