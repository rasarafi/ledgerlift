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
    function get_setting(string $key, mixed $default = ''): mixed
    {
        return app(Settings::class)->get($key, $default);
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

if (! function_exists('htmlsc')) {
    /**
     * echo_helper.php: htmlsc(), for output that is built outside Blade (JSON). Blade's {{ }}
     * escapes the same characters.
     */
    function htmlsc(?string $output): ?string
    {
        return $output === null ? null : htmlspecialchars($output, ENT_QUOTES | ENT_IGNORE);
    }
}

if (! function_exists('format_client')) {
    /**
     * client_helper.php: format_client(), "<title> <name> <surname>"; the title only when asked
     * for, translated for the four known ones. Takes a client row or any row that joins one.
     */
    function format_client(?object $client, bool $showTitle = true): string
    {
        if ($client === null || empty($client->client_name)) {
            return '';
        }

        $title = '';

        if ($showTitle && ! empty($client->client_title)) {
            $known = in_array($client->client_title, ['mr', 'mrs', 'doctor', 'professor'], true);
            $title = ucfirst($known ? __('ip.'.$client->client_title) : $client->client_title).' ';
        }

        return $title.$client->client_name.(empty($client->client_surname) ? '' : ' '.$client->client_surname);
    }
}

if (! function_exists('format_gender')) {
    /** client_helper.php: format_gender(). */
    function format_gender(mixed $gender): string
    {
        return match (true) {
            $gender == 0 => __('ip.gender_male'),
            $gender == 1 => __('ip.gender_female'),
            default => __('ip.gender_other'),
        };
    }
}

if (! function_exists('format_avs')) {
    /** custom_values_helper.php: format_avs(), 7561234567897 as 756.1234.5678.97. */
    function format_avs(?string $number): ?string
    {
        if (! $number || ! preg_match('/(\d{3})(\d{4})(\d{4})(\d{2})/', $number, $matches)) {
            return $number;
        }

        return $matches[1].'.'.$matches[2].'.'.$matches[3].'.'.$matches[4];
    }
}

if (! function_exists('format_date')) {
    /** custom_values_helper.php: format_date(). */
    function format_date(?string $date): string
    {
        return $date == null ? '' : date_from_mysql($date);
    }
}
