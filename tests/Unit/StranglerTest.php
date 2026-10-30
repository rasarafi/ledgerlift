<?php

use App\Support\Strangler;
use Tests\TestCase;

uses(TestCase::class);

it('keeps migrated modules on this app', function (string $path) {
    expect(Strangler::url($path))->toBe(url($path));
})->with(['dashboard', 'sessions/logout', 'clients/form', 'invoices/status/all/15', 'payment_methods/index', 'filter/ajax/filter_clients', 'tax_rates', 'clients?x=1']);

it('sends every other module to the legacy app', function (string $path, string $expected) {
    config(['ledgerlift.legacy_url' => 'http://legacy.test/']);

    expect(Strangler::url($path))->toBe($expected);
})->with([
    ['quotes/index', 'http://legacy.test/quotes/index'],
    ['/reports/invoice_aging', 'http://legacy.test/reports/invoice_aging'],
    ['settings', 'http://legacy.test/settings'],
    ['users/form/1', 'http://legacy.test/users/form/1'],
]);

it('defaults the legacy url to the compose legacy service', function () {
    expect(config('ledgerlift.legacy_url'))->toBe('http://localhost:8091')
        ->and(config('ledgerlift.migrated_modules'))->toBe([
            'dashboard', 'sessions', 'clients', 'products', 'families', 'units', 'tax_rates',
            'invoice_groups', 'invoices', 'payments', 'payment_methods', 'filter',
        ]);
});
