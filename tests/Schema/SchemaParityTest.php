<?php

use Illuminate\Support\Facades\Artisan;
use Tests\Support\Mysql;
use Tests\Support\Pristine;
use Tests\Support\SchemaReader;
use Tests\TestCase;

uses(TestCase::class);

const SCHEMA_DB = 'ledgerlift_schema_test';

beforeEach(function () {
    if (! env('LEGACY_DB')) {
        $this->markTestSkipped('LEGACY_DB is unset; the schema check needs a fresh legacy install to compare against.');
    }
});

/**
 * Runs the migrations from scratch on the schema database only. `--database`
 * does not move the destructive-command guard (it keys on the default
 * connection), so the target name is checked here as well.
 */
function migrateSchemaDatabase(): void
{
    expect(SCHEMA_DB)->toEndWith('_test');
    Mysql::connection()->statement('CREATE DATABASE IF NOT EXISTS `'.SCHEMA_DB.'`');
    Mysql::connection(SCHEMA_DB);

    $exit = Artisan::call('migrate:fresh', ['--database' => Mysql::name(SCHEMA_DB), '--force' => true]);
    expect($exit)->toBe(0, Artisan::output());
}

it('reproduces the legacy schema exactly', function () {
    $pristine = Pristine::ensure();
    migrateSchemaDatabase();

    $laravel = SchemaReader::describe(SCHEMA_DB);
    unset($laravel['tables']['migrations'], $laravel['columns']['migrations'], $laravel['indexes']['migrations']);

    $legacy = SchemaReader::describe($pristine);

    // ip_users: the pristine install also holds the admin created by the
    // container's create_default_user step (credentials come from env, not
    // from the install defaults), so only its AUTO_INCREMENT counter differs.
    $legacy['tables']['ip_users']['auto_increment'] = $laravel['tables']['ip_users']['auto_increment'];

    expect(SchemaReader::diff($legacy, $laravel))->toBe([]);
    expect($laravel['tables'])->toHaveCount(42);
});

/**
 * Column values that legitimately differ between two installs, with the reason.
 * Everything else must match exactly.
 *
 * @return list<array<string, mixed>>
 */
function installRows(string $database, string $table, string $orderBy): array
{
    $rows = array_map(fn ($row) => (array) $row, Mysql::connection($database)->select("SELECT * FROM `$table` ORDER BY $orderBy"));

    foreach ($rows as &$row) {
        // Mdl_setup::install_default_settings: cron_key = generate_secure_token(8), random per install.
        if ($table === 'ip_settings' && $row['setting_key'] === 'cron_key') {
            expect($row['setting_value'])->toMatch('/^[0-9a-f]{16}$/');
            $row['setting_value'] = '<random 16 hex>';
        }
        // Mdl_setup::save_version stores time() of the install run.
        if ($table === 'ip_versions') {
            expect($row['version_date_applied'])->toMatch('/^\d{10}$/');
            $row['version_date_applied'] = '<install time>';
        }
    }

    return $rows;
}

it('seeds exactly the rows a fresh legacy install creates', function (string $table, string $orderBy) {
    $pristine = Pristine::ensure();
    migrateSchemaDatabase();

    $expected = installRows($pristine, $table, $orderBy);

    expect($expected)->not->toBeEmpty();
    expect(installRows(SCHEMA_DB, $table, $orderBy))->toEqual($expected);
})->with([
    ['ip_settings', 'setting_key, setting_id'],
    ['ip_invoice_groups', 'invoice_group_id'],
    ['ip_payment_methods', 'payment_method_id'],
    ['ip_versions', 'version_id'],
]);

it('leaves every other table empty, as the legacy install does apart from the admin user', function () {
    $pristine = Pristine::ensure();
    migrateSchemaDatabase();

    $populated = ['ip_settings', 'ip_invoice_groups', 'ip_payment_methods', 'ip_versions'];
    $count = fn (string $db, string $table): int => (int) Mysql::connection($db)->scalar("SELECT COUNT(*) FROM `$table`");

    foreach (array_keys(SchemaReader::describe($pristine)['tables']) as $table) {
        if (in_array($table, $populated, true) || $table === 'ip_users') {
            continue;
        }
        expect($count(SCHEMA_DB, $table))->toBe($count($pristine, $table), $table);
    }
    expect($count(SCHEMA_DB, 'ip_users'))->toBe(0);
});
