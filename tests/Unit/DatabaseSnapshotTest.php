<?php

use App\Database\DatabaseSnapshot;
use App\Database\Mysql;
use Illuminate\Database\Connection;
use Tests\TestCase;

uses(TestCase::class);

const SNAPSHOT_SOURCE = 'ledgerlift_snapshot_source_test';
const SNAPSHOT_TARGET = 'ledgerlift_snapshot_target_test';

beforeEach(function () {
    $admin = Mysql::connection();
    $admin->statement('DROP DATABASE IF EXISTS `'.SNAPSHOT_SOURCE.'`');
    $admin->statement('DROP DATABASE IF EXISTS `'.SNAPSHOT_TARGET.'`');
    $admin->statement('CREATE DATABASE `'.SNAPSHOT_SOURCE.'` CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci');
});

afterEach(function () {
    $admin = Mysql::connection();
    $admin->statement('DROP DATABASE IF EXISTS `'.SNAPSHOT_SOURCE.'`');
    $admin->statement('DROP DATABASE IF EXISTS `'.SNAPSHOT_TARGET.'`');
});

it('refuses a database whose name does not end in _test', function () {
    expect(fn () => DatabaseSnapshot::copy('ledgerlift', SNAPSHOT_TARGET))->toThrow(InvalidArgumentException::class);
    expect(fn () => DatabaseSnapshot::copy(SNAPSHOT_SOURCE, 'ledgerlift'))->toThrow(InvalidArgumentException::class);
});

it('refuses a name with a trailing newline after _test', function () {
    expect(fn () => DatabaseSnapshot::copy(SNAPSHOT_SOURCE, "ledgerlift_test\n"))->toThrow(InvalidArgumentException::class);
    expect(fn () => DatabaseSnapshot::copy("ledgerlift_test\n", SNAPSHOT_TARGET))->toThrow(InvalidArgumentException::class);
});

it('reproduces rows, the AUTO_INCREMENT counter and the database charset', function () {
    $admin = Mysql::connection();
    $admin->statement('CREATE TABLE `'.SNAPSHOT_SOURCE.'`.ip_invoice_groups (
        invoice_group_id int NOT NULL AUTO_INCREMENT,
        invoice_group_name text,
        PRIMARY KEY (invoice_group_id)
    ) ENGINE=MyISAM');
    $admin->statement('INSERT INTO `'.SNAPSHOT_SOURCE.'`.ip_invoice_groups VALUES (3, "Invoice Default"), (4, "Quote Default")');
    // A counter ahead of MAX(id), as after a deleted row: INSERT ... SELECT alone would not carry it.
    $admin->statement('ALTER TABLE `'.SNAPSHOT_SOURCE.'`.ip_invoice_groups AUTO_INCREMENT=9');

    DatabaseSnapshot::copy(SNAPSHOT_SOURCE, SNAPSHOT_TARGET);

    expect($admin->select('SELECT * FROM `'.SNAPSHOT_TARGET.'`.ip_invoice_groups ORDER BY invoice_group_id'))
        ->toEqual($admin->select('SELECT * FROM `'.SNAPSHOT_SOURCE.'`.ip_invoice_groups ORDER BY invoice_group_id'));

    $admin->statement('SET SESSION information_schema_stats_expiry = 0');
    $next = $admin->scalar('SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [SNAPSHOT_TARGET, 'ip_invoice_groups']);
    expect((int) $next)->toBe(9);

    $charset = $admin->scalar('SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [SNAPSHOT_TARGET]);
    expect($charset)->toBe('utf8mb3_general_ci');
});

it('replaces an existing target', function () {
    $admin = Mysql::connection();
    $admin->statement('CREATE DATABASE `'.SNAPSHOT_TARGET.'`');
    $admin->statement('CREATE TABLE `'.SNAPSHOT_TARGET.'`.stale (id int)');
    $admin->statement('CREATE TABLE `'.SNAPSHOT_SOURCE.'`.fresh (id int)');

    DatabaseSnapshot::copy(SNAPSHOT_SOURCE, SNAPSHOT_TARGET);

    $tables = array_column($admin->select('SELECT TABLE_NAME AS t FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [SNAPSHOT_TARGET]), 't');
    expect($tables)->toBe(['fresh']);
});

it('restore() refuses names that do not end in _test, like copy()', function () {
    expect(fn () => DatabaseSnapshot::restore('ledgerlift', SNAPSHOT_TARGET))->toThrow(InvalidArgumentException::class);
    expect(fn () => DatabaseSnapshot::restore(SNAPSHOT_SOURCE, 'ledgerlift'))->toThrow(InvalidArgumentException::class);
    expect(fn () => DatabaseSnapshot::restore(SNAPSHOT_SOURCE, SNAPSHOT_SOURCE))->toThrow(InvalidArgumentException::class);
});

/** A source with a counter ahead of MAX(id), copied to the target, which is then modified. */
function snapshotWithModifiedTarget(): Connection
{
    $admin = Mysql::connection();
    $admin->statement('CREATE TABLE `'.SNAPSHOT_SOURCE.'`.ip_invoice_groups (
        invoice_group_id int NOT NULL AUTO_INCREMENT,
        invoice_group_name text,
        PRIMARY KEY (invoice_group_id)
    ) ENGINE=MyISAM');
    $admin->statement('CREATE TABLE `'.SNAPSHOT_SOURCE.'`.ip_login_log (login_name varchar(100) NOT NULL, log_count int, PRIMARY KEY (login_name)) ENGINE=MyISAM');
    $admin->statement('INSERT INTO `'.SNAPSHOT_SOURCE.'`.ip_invoice_groups VALUES (3, "Invoice Default"), (4, "Quote Default")');
    $admin->statement('ALTER TABLE `'.SNAPSHOT_SOURCE.'`.ip_invoice_groups AUTO_INCREMENT=9');

    DatabaseSnapshot::copy(SNAPSHOT_SOURCE, SNAPSHOT_TARGET);

    $admin->statement('UPDATE `'.SNAPSHOT_TARGET.'`.ip_invoice_groups SET invoice_group_name = "Changed" WHERE invoice_group_id = 3');
    $admin->statement('DELETE FROM `'.SNAPSHOT_TARGET.'`.ip_invoice_groups WHERE invoice_group_id = 4');
    $admin->statement('INSERT INTO `'.SNAPSHOT_TARGET.'`.ip_invoice_groups (invoice_group_name) VALUES ("New 1"), ("New 2"), ("New 3")');
    $admin->statement('INSERT INTO `'.SNAPSHOT_TARGET.'`.ip_login_log VALUES ("someone", 1)');

    return $admin;
}

function autoIncrement(string $database, string $table): int
{
    $admin = Mysql::connection();
    $admin->statement('SET SESSION information_schema_stats_expiry = 0');

    return (int) $admin->scalar('SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$database, $table]);
}

it('restore() puts back rows and the AUTO_INCREMENT counter in place, without recreating the database', function () {
    $admin = snapshotWithModifiedTarget();
    expect(autoIncrement(SNAPSHOT_TARGET, 'ip_invoice_groups'))->toBe(12);
    // copy() recreates the database with the source's charset; an in-place restore keeps this one.
    $admin->statement('ALTER DATABASE `'.SNAPSHOT_TARGET.'` CHARACTER SET latin1 COLLATE latin1_swedish_ci');

    DatabaseSnapshot::restore(SNAPSHOT_SOURCE, SNAPSHOT_TARGET);

    expect($admin->select('SELECT * FROM `'.SNAPSHOT_TARGET.'`.ip_invoice_groups ORDER BY invoice_group_id'))
        ->toEqual($admin->select('SELECT * FROM `'.SNAPSHOT_SOURCE.'`.ip_invoice_groups ORDER BY invoice_group_id'))
        ->and($admin->select('SELECT * FROM `'.SNAPSHOT_TARGET.'`.ip_login_log'))->toBe([])
        ->and(autoIncrement(SNAPSHOT_TARGET, 'ip_invoice_groups'))->toBe(9)
        ->and($admin->scalar('SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [SNAPSHOT_TARGET]))->toBe('latin1_swedish_ci');
});

it('restore() falls back to copy() when the table sets differ', function () {
    $admin = snapshotWithModifiedTarget();
    $admin->statement('CREATE TABLE `'.SNAPSHOT_TARGET.'`.stale (id int)');

    DatabaseSnapshot::restore(SNAPSHOT_SOURCE, SNAPSHOT_TARGET);

    $tables = array_column($admin->select('SELECT TABLE_NAME AS t FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY t', [SNAPSHOT_TARGET]), 't');
    expect($tables)->toBe(['ip_invoice_groups', 'ip_login_log'])
        ->and(autoIncrement(SNAPSHOT_TARGET, 'ip_invoice_groups'))->toBe(9);
});

it('restore() falls back to copy() when a table structure differs', function () {
    $admin = snapshotWithModifiedTarget();
    // As legacy's tax_rate_decimal_places setting does to ip_tax_rates: same table, altered column.
    $admin->statement('ALTER TABLE `'.SNAPSHOT_TARGET.'`.ip_login_log MODIFY log_count bigint');

    DatabaseSnapshot::restore(SNAPSHOT_SOURCE, SNAPSHOT_TARGET);

    $type = $admin->scalar('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?', [SNAPSHOT_TARGET, 'ip_login_log', 'log_count']);
    expect($type)->toBe('int')
        ->and($admin->select('SELECT * FROM `'.SNAPSHOT_TARGET.'`.ip_login_log'))->toBe([]);
});

it('restore() creates a missing target', function () {
    Mysql::connection()->statement('CREATE TABLE `'.SNAPSHOT_SOURCE.'`.fresh (id int)');

    DatabaseSnapshot::restore(SNAPSHOT_SOURCE, SNAPSHOT_TARGET);

    expect(Mysql::connection()->scalar('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [SNAPSHOT_TARGET]))->toBe(1);
});
