<?php

use App\Support\DateFormat;
use App\Support\NumberFormat;

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
