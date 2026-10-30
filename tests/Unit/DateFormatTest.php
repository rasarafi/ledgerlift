<?php

use App\Support\DateFormat;
use App\Support\Settings;

// Expected values were checked against application/helpers/date_helper.php of InvoicePlane v1.7.2.

function dateFormat(string $format = 'm/d/Y'): DateFormat
{
    return new DateFormat(new Settings(['date_format' => $format]));
}

it('converts a displayed date to the MySQL format', function () {
    expect(dateFormat()->toMysql('10/30/2026'))->toBe('2026-10-30')
        ->and(dateFormat('d.m.Y')->toMysql('30.10.2026'))->toBe('2026-10-30')
        ->and(dateFormat('Y-m-d')->toMysql('2026-10-30'))->toBe('2026-10-30');
});

it('returns an empty string when the date does not parse', function () {
    expect(dateFormat()->toMysql('garbage'))->toBe('')
        ->and(dateFormat()->toMysql(''))->toBe('')
        ->and(dateFormat()->toMysql('2026-10-30'))->toBe('');
});

it('converts a MySQL date to the display format', function () {
    expect(dateFormat()->fromMysql('2026-10-30'))->toBe('10/30/2026')
        ->and(dateFormat('d-M-Y')->fromMysql('2026-10-30'))->toBe('30-Oct-2026')
        ->and(dateFormat('j.n.Y')->fromMysql('2026-01-05'))->toBe('5.1.2026');
});

it('returns an empty string for an empty or unparseable MySQL date', function () {
    expect(dateFormat()->fromMysql(null))->toBe('')
        ->and(dateFormat()->fromMysql(''))->toBe('')
        ->and(dateFormat()->fromMysql('not a date'))->toBe('');
});

it('returns the raw value while $_POST is non-empty unless told to ignore it', function () {
    // date_helper.php:84: date_from_mysql() hands the value back untouched on a POST.
    $_POST = ['x' => '1'];

    try {
        expect(dateFormat()->fromMysql('2026-10-30'))->toBe('2026-10-30')
            ->and(dateFormat()->fromMysql('2026-10-30', ignorePostCheck: true))->toBe('10/30/2026');
    } finally {
        $_POST = [];
    }
});

it('maps the display format to its bootstrap-datepicker pattern', function () {
    // date_formats() in date_helper.php
    expect(dateFormat()->datepicker())->toBe('mm/dd/yyyy')
        ->and(dateFormat('d-M-Y')->datepicker())->toBe('dd-M-yyyy')
        ->and(dateFormat('j.n.Y')->datepicker())->toBe('d.m.yyyy')
        ->and(dateFormat('d M,Y')->datepicker())->toBe('dd M,yyyy')
        ->and(dateFormat('Y.m.d')->datepicker())->toBe('yyyy.mm.dd');
});
