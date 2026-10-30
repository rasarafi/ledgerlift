<?php

use Tests\Support\DatabaseSnapshot;
use Tests\Support\Mysql;
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
