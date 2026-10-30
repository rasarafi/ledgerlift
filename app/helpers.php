<?php

use App\Support\DateFormat;
use App\Support\NumberFormat;
use App\Support\Settings;

if (! function_exists('format_currency')) {
    function format_currency(float|string|null $amount): string
    {
        return app(NumberFormat::class)->currency($amount);
    }
}

if (! function_exists('format_amount')) {
    function format_amount(float|string|null $amount = null): ?string
    {
        return app(NumberFormat::class)->amount($amount);
    }
}

if (! function_exists('format_quantity')) {
    function format_quantity(float|string|null $amount = null): ?string
    {
        return app(NumberFormat::class)->quantity($amount);
    }
}

if (! function_exists('date_from_mysql')) {
    function date_from_mysql(?string $date, bool $ignore_post_check = false): string
    {
        return app(DateFormat::class)->fromMysql($date, $ignore_post_check);
    }
}

if (! function_exists('get_setting')) {
    /**
     * As the legacy get_setting(): an unset or empty setting gives $default.
     */
    function get_setting(string $key, mixed $default = '', bool $escape = false): mixed
    {
        $value = app(Settings::class)->get($key, $default);

        return $escape && is_string($value) ? e($value) : $value;
    }
}

if (! function_exists('core_asset')) {
    /**
     * URL of a file under assets/core, versioned like _core_asset() in echo_helper.php.
     */
    function core_asset(string $asset): string
    {
        return url('assets/core/'.$asset).'?v='.get_setting('current_version');
    }
}

if (! function_exists('theme_asset')) {
    /**
     * URL of a file of the selected theme, as _theme_asset() in echo_helper.php.
     */
    function theme_asset(string $asset): string
    {
        return url('assets/'.get_setting('system_theme', 'invoiceplane').'/'.$asset).'?v='.get_setting('current_version');
    }
}
