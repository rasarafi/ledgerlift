<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
 * ledgerlift:demo-seed, which the new app's container runs on every start. The full first-start
 * path (pristine snapshot, copy, demo company in both databases) needs the compose stack and
 * runs there; here: the guard and the no-op.
 */

it('refuses a legacy or new database whose name does not end in _test, and writes nothing', function (string $setting, string $value) {
    $before = DB::table('ip_users')->count();
    config([$setting => $value]);

    expect(Artisan::call('ledgerlift:demo-seed'))->toBe(1)
        ->and(Artisan::output())->toContain("'$value' is not a database whose name ends in _test");
    expect(DB::table('ip_users')->count())->toBe($before);
})->with([
    'legacy' => ['ledgerlift.legacy_database', 'ledgerlift_legacy'],
    'legacy unset' => ['ledgerlift.legacy_database', ''],
    'new' => ['database.connections.mysql.database', 'ledgerlift'],
]);

it('does nothing once the new database has a user', function () {
    // A legacy database that does not exist: any attempt to read or seed it would fail.
    config(['ledgerlift.legacy_database' => 'ledgerlift_absent_test']);
    $clients = DB::table('ip_clients')->count();

    expect(Artisan::call('ledgerlift:demo-seed'))->toBe(0)
        ->and(Artisan::output())->toContain('ledgerlift_test already has users; nothing to seed.');
    expect(DB::table('ip_clients')->count())->toBe($clients)
        ->and(DB::table('ip_users')->count())->toBe(1);
});
