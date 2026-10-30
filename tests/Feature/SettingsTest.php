<?php

use App\Support\Settings;
use Illuminate\Support\Facades\DB;

it('loads the install defaults', function () {
    $settings = app(Settings::class);

    expect($settings->get('date_format'))->toBe('m/d/Y')
        ->and($settings->get('currency_symbol'))->toBe('$')
        ->and($settings->get('currency_symbol_placement'))->toBe('before')
        ->and($settings->get('thousands_separator'))->toBe(',')
        ->and($settings->get('decimal_point'))->toBe('.')
        ->and($settings->get('invoices_due_after'))->toBe('30')
        ->and($settings->get('default_invoice_group'))->toBe('3');
});

it('returns the default for a missing key', function () {
    expect(app(Settings::class)->get('missing', 'x'))->toBe('x')
        ->and(app(Settings::class)->get('missing'))->toBeNull();
});

it('treats a stored empty string as unset', function () {
    DB::table('ip_settings')->insert(['setting_key' => 'blank_one', 'setting_value' => '']);

    expect(app(Settings::class)->get('blank_one', 'fallback'))->toBe('fallback');
});

it('lets the last row win when a key appears twice, as legacy does', function () {
    // Mdl_settings::load_settings() overwrites in row order.
    DB::table('ip_settings')->insert([
        ['setting_key' => 'dup_key', 'setting_value' => 'first'],
        ['setting_key' => 'dup_key', 'setting_value' => 'second'],
    ]);

    expect(app(Settings::class)->get('dup_key'))->toBe('second');
});

it('loads the table once per scope', function () {
    $settings = app(Settings::class);
    $settings->get('date_format');

    DB::table('ip_settings')->where('setting_key', 'date_format')->update(['setting_value' => 'd/m/Y']);

    expect(app(Settings::class))->toBe($settings)
        ->and(app(Settings::class)->get('date_format'))->toBe('m/d/Y');
});

it('drives the global helpers from the stored settings', function () {
    DB::table('ip_settings')->where('setting_key', 'currency_symbol_placement')->update(['setting_value' => 'afterspace']);
    DB::table('ip_settings')->where('setting_key', 'date_format')->update(['setting_value' => 'd.m.Y']);

    expect(format_currency(1234.5))->toBe('1,234.50&nbsp;$')
        ->and(format_amount('12'))->toBe('12.00')
        ->and(format_quantity('1.5'))->toBe('1.50')
        ->and(date_from_mysql('2026-10-30'))->toBe('30.10.2026');
});

it('exposes the newest applied migration as current_version, as legacy does', function () {
    expect(app(Settings::class)->get('current_version'))->toBe('1.7.2');
});

it('picks current_version by date applied before file name, like the legacy ordering', function () {
    // The legacy installer applied 019_1.4.7.sql last, so its install reports 1.4.7.
    DB::table('ip_versions')->insert([
        'version_date_applied' => (string) (time() + 10), 'version_file' => '019_1.4.7.sql', 'version_sql_errors' => 0,
    ]);

    expect(app(Settings::class)->get('current_version'))->toBe('1.4.7');
});
