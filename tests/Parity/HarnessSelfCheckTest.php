<?php

use App\Database\DatabaseSnapshot;
use App\Database\Mysql;
use PHPUnit\Framework\AssertionFailedError;
use Tests\Parity\Support\AppClient;
use Tests\Parity\Support\DbDiff;
use Tests\Parity\Support\ParityDatabases;

// These tests check the harness itself: that it can fail, that setting variants reach
// both apps, and that normalise.php hides exactly the run-to-run noise and no more.

it('self-check: reports a difference injected into NEW_DB and fails toHaveNoDifferences', function () {
    $result = parity(function (AppClient $app, string $side) {
        if ($side === 'new') {
            $db = ParityDatabases::connection($side);
            $db->table('ip_clients')->where('client_id', 1)->update(['client_name' => 'Injected Name']);
            $db->table('ip_families')->insert(['family_id' => 3, 'family_name' => 'Injected Family']);
        }

        return [];
    });

    expect($result->diff)->toContain(
        ['table' => 'ip_clients', 'pk' => '1', 'column' => 'client_name', 'legacy' => 'Ashgrove Joinery', 'new' => 'Injected Name'],
        ['table' => 'ip_families', 'pk' => '3', 'column' => DbDiff::ROW, 'legacy' => null, 'new' => '{"family_id":3,"family_name":"Injected Family"}'],
    )->toHaveCount(2);

    expect(fn () => expect($result)->toHaveNoDifferences())
        ->toThrow(AssertionFailedError::class, 'Injected Name');
});

it('self-check: a settings variant reaches both apps', function () {
    $title = fn (AppClient $app) => [$app->get('sessions/login')->status(), $app->text('//title')];

    $defaults = parity($title);
    expect($defaults->legacy)->toBe([200, 'InvoicePlane - Login'])
        ->and($defaults->new)->toBe([200, 'ledgerlift - Login']);
    expect(fn () => expect($defaults)->toMatchAcrossApps())->toThrow(AssertionFailedError::class, 'different values');

    // custom_title is not an install default (insert path); currency_symbol is (update path).
    $variant = parity($title, ['custom_title' => 'Parity Check Ledger', 'currency_symbol' => 'EUR ']);
    expect($variant->legacy)->toBe([200, 'Parity Check Ledger - Login']);
    expect($variant)->toMatchAcrossApps()->toHaveNoDifferences();

    foreach (['legacy', 'new'] as $side) {
        expect(ParityDatabases::connection($side)->table('ip_settings')->whereIn('setting_key', ['custom_title', 'currency_symbol'])
            ->orderBy('setting_key')->pluck('setting_value', 'setting_key')->all())
            ->toBe(['currency_symbol' => 'EUR ', 'custom_title' => 'Parity Check Ledger']);
    }

    ParityDatabases::reset();
    expect(ParityDatabases::connection('legacy')->table('ip_settings')->where('setting_key', 'custom_title')->exists())->toBeFalse();
});

it('self-check: two identical legacy runs differ only in normalised columns', function () {
    $copy = 'ledgerlift_parity_rerun_test';
    $failTwice = function (): void {
        $app = new AppClient((string) env('LEGACY_URL'));
        $app->get('sessions/login');
        $app->post('sessions/login', ['email' => 'admin@ledgerlift.test', 'password' => 'wrong', 'btn_login' => 'true']);
        $app->post('sessions/login', ['email' => 'admin@ledgerlift.test', 'password' => 'wrong', 'btn_login' => 'true']);
    };
    $legacyDb = (string) env('LEGACY_DB');

    ParityDatabases::reset();
    $failTwice();
    DatabaseSnapshot::copy($legacyDb, $copy);

    // date('c') has one-second resolution; make sure the second run lands in a later second.
    sleep(1);
    ParityDatabases::reset();
    $failTwice();

    // Every raw difference is in a normalise.php column. Whether the timestamps differ at all
    // depends on the clock (WSL can step it), so their count is only bounded, not required.
    $raw = DbDiff::compare($copy, $legacyDb, normalise: []);
    $normalised = array_keys(require __DIR__.'/normalise.php');
    foreach ($raw as $difference) {
        expect($normalised)->toContain($difference['table'].'.'.$difference['column']);
    }
    expect(count($raw))->toBeLessThanOrEqual(2);
    expect(DbDiff::compare($copy, $legacyDb))->toBe([]);

    Mysql::connection()->statement("DROP DATABASE `$copy`");
});
