<?php

namespace App\Support;

/**
 * Port of application/helpers/number_helper.php (InvoicePlane v1.7.2).
 */
final class NumberFormat
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * format_currency(): the amount with the currency symbol placed per settings.
     */
    public function currency(float|string|null $amount): string
    {
        $symbol = $this->setting('currency_symbol');
        $placement = $this->setting('currency_symbol_placement');
        $number = $this->number($amount, 'tax_rate_decimal_places');

        return match ($placement) {
            'before' => $symbol.$number,
            'afterspace' => $number.'&nbsp;'.$symbol,
            default => $number.$symbol,
        };
    }

    /**
     * format_amount(): no symbol; null for any falsy value (0, '0', '', null).
     */
    public function amount(float|string|null $amount): ?string
    {
        return $amount ? $this->number($amount, 'tax_rate_decimal_places') : null;
    }

    /**
     * format_quantity(): like amount() but with default_item_decimals.
     */
    public function quantity(float|string|null $q): ?string
    {
        return $q ? $this->number($q, 'default_item_decimals') : null;
    }

    /**
     * standardize_amount(): user-formatted input to a DB-style number string.
     * Numeric and falsy values come back unchanged.
     */
    public function standardize(string|int|float|null $amount): string|int|float|null
    {
        if (! $amount || is_numeric($amount)) {
            return $amount;
        }

        $thousands = $this->setting('thousands_separator');
        $decimalPoint = $this->setting('decimal_point');

        // number_helper.php:91-93: "1.234.567" with '.' as thousands separator
        // reads as a decimal comma at the last dot.
        if ($thousands === '.' && ! mb_substr_count($amount, ',') && mb_substr_count($amount, '.') > 1) {
            $amount[(int) mb_strrpos($amount, '.')] = ',';
        }

        return $thousands !== ''
            ? strtr($amount, [$thousands => '', $decimalPoint => '.'])
            : strtr($amount, [$decimalPoint => '.']);
    }

    private function number(float|string|null $amount, string $decimalsSetting): string
    {
        $decimalPoint = $this->setting('decimal_point');
        $decimals = $decimalPoint !== '' ? (int) $this->setting($decimalsSetting) : 0;
        $value = (float) (is_numeric($amount) ? $amount : $this->standardize($amount));

        return LegacyMath::numberFormat($value, $decimals, $decimalPoint, $this->setting('thousands_separator'));
    }

    private function setting(string $key): string
    {
        return (string) $this->settings->get($key, '');
    }
}
