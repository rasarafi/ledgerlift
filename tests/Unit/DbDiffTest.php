<?php

use Tests\Parity\Support\DbDiff;
use Tests\Support\Mysql;
use Tests\TestCase;

uses(TestCase::class);

const DIFF_A = 'ledgerlift_diff_a_test';
const DIFF_B = 'ledgerlift_diff_b_test';

beforeEach(function () {
    $admin = Mysql::connection();

    foreach ([DIFF_A, DIFF_B] as $db) {
        $admin->statement("DROP DATABASE IF EXISTS `$db`");
        $admin->statement("CREATE DATABASE `$db`");
        $admin->statement("CREATE TABLE `$db`.ip_clients (client_id int NOT NULL, client_name text, client_date_modified datetime, PRIMARY KEY (client_id)) ENGINE=MyISAM");
        $admin->statement("CREATE TABLE `$db`.ip_quote_item_amounts (quote_id int NOT NULL, item_id int NOT NULL, amount decimal(20,2), PRIMARY KEY (quote_id, item_id)) ENGINE=MyISAM");
        // No primary key, as in the legacy schema.
        $admin->statement("CREATE TABLE `$db`.ip_sessions (id varchar(40), data blob) ENGINE=MyISAM");
        $admin->statement("CREATE TABLE `$db`.migrations (id int NOT NULL, migration varchar(255), PRIMARY KEY (id)) ENGINE=MyISAM");
        $admin->statement("INSERT INTO `$db`.ip_clients VALUES (1, 'Ashgrove Joinery', '2026-01-01 00:00:00'), (2, 'Bellmont Lighting Co', '2026-01-01 00:00:00')");
        $admin->statement("INSERT INTO `$db`.ip_quote_item_amounts VALUES (1, 1, 10.00), (1, 2, 20.00)");
    }
});

afterEach(function () {
    foreach ([DIFF_A, DIFF_B] as $db) {
        Mysql::connection()->statement("DROP DATABASE IF EXISTS `$db`");
    }
});

it('finds nothing in identical databases', function () {
    expect(DbDiff::compare(DIFF_A, DIFF_B))->toBe([]);
});

it('reports changed values, and rows present on one side only, ordered by primary key', function () {
    $admin = Mysql::connection();
    $admin->statement('UPDATE `'.DIFF_B.'`.ip_clients SET client_name = ? WHERE client_id = 2', ['Bellmont Lights']);
    $admin->statement('DELETE FROM `'.DIFF_B.'`.ip_clients WHERE client_id = 1');
    $admin->statement('INSERT INTO `'.DIFF_B.'`.ip_quote_item_amounts VALUES (2, 1, 5.50)');
    $admin->statement('UPDATE `'.DIFF_B.'`.ip_quote_item_amounts SET amount = 21.00 WHERE quote_id = 1 AND item_id = 2');

    expect(DbDiff::compare(DIFF_A, DIFF_B))->toBe([
        ['table' => 'ip_clients', 'pk' => '1', 'column' => DbDiff::ROW, 'legacy' => '{"client_id":1,"client_name":"Ashgrove Joinery","client_date_modified":"2026-01-01 00:00:00"}', 'new' => null],
        ['table' => 'ip_clients', 'pk' => '2', 'column' => 'client_name', 'legacy' => 'Bellmont Lighting Co', 'new' => 'Bellmont Lights'],
        ['table' => 'ip_quote_item_amounts', 'pk' => '1,2', 'column' => 'amount', 'legacy' => '20.00', 'new' => '21.00'],
        ['table' => 'ip_quote_item_amounts', 'pk' => '2,1', 'column' => DbDiff::ROW, 'legacy' => null, 'new' => '{"quote_id":2,"item_id":1,"amount":"5.50"}'],
    ]);
});

it('skips normalise.php columns unless asked not to, and ignores ip_sessions and non-ip tables', function () {
    $admin = Mysql::connection();
    $admin->statement('UPDATE `'.DIFF_B.'`.ip_clients SET client_date_modified = NOW()');
    $admin->statement('INSERT INTO `'.DIFF_B.'`.ip_sessions VALUES ("abc", "x")');
    $admin->statement('INSERT INTO `'.DIFF_B.'`.migrations VALUES (1, "x")');

    expect(DbDiff::compare(DIFF_A, DIFF_B, ['ip_clients.client_date_modified' => 'test']))->toBe([])
        ->and(DbDiff::compare(DIFF_A, DIFF_B, []))->toHaveCount(2);
});

it('reports a table that exists on one side only', function () {
    Mysql::connection()->statement('CREATE TABLE `'.DIFF_B.'`.ip_extra (id int NOT NULL, PRIMARY KEY (id))');

    expect(DbDiff::compare(DIFF_A, DIFF_B))->toBe([
        ['table' => 'ip_extra', 'pk' => null, 'column' => DbDiff::TABLE, 'legacy' => null, 'new' => 'exists'],
    ]);
});

it('formats a diff as a table', function () {
    $table = DbDiff::format([
        ['table' => 'ip_clients', 'pk' => '2', 'column' => 'client_name', 'legacy' => 'Bellmont Lighting Co', 'new' => null],
    ]);

    expect($table)->toContain('table')->toContain('ip_clients')->toContain('Bellmont Lighting Co')->toContain('NULL');
});
