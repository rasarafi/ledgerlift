<?php

use App\Database\Mysql;
use Tests\Support\SchemaReader;
use Tests\TestCase;

uses(TestCase::class);

const READER_DB = 'ledgerlift_reader_test';

beforeEach(function () {
    $admin = Mysql::connection();
    $admin->statement('DROP DATABASE IF EXISTS `'.READER_DB.'`');
    $admin->statement('CREATE DATABASE `'.READER_DB.'` CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci');
    $admin->statement('CREATE TABLE `'.READER_DB.'`.t (
        id int NOT NULL AUTO_INCREMENT,
        name varchar(100) DEFAULT NULL,
        note text,
        PRIMARY KEY (id),
        UNIQUE KEY by_name (name(20), id)
    ) ENGINE=MyISAM AUTO_INCREMENT=7');
});

afterEach(function () {
    Mysql::connection()->statement('DROP DATABASE IF EXISTS `'.READER_DB.'`');
});

it('describes tables, columns in order, and indexes with prefix lengths', function () {
    $schema = SchemaReader::describe(READER_DB);

    expect($schema['tables']['t'])->toBe(['engine' => 'MyISAM', 'collation' => 'utf8mb3_general_ci', 'auto_increment' => 7]);
    expect(array_column($schema['columns']['t'], 'name'))->toBe(['id', 'name', 'note']);
    expect($schema['columns']['t'][2])->toMatchArray(['column_type' => 'varchar(100)', 'is_nullable' => 'YES', 'collation' => 'utf8mb3_general_ci']);
    expect($schema['indexes']['t']['PRIMARY'])->toBe(['non_unique' => 0, 'columns' => ['id']]);
    expect($schema['indexes']['t']['by_name'])->toBe(['non_unique' => 0, 'columns' => ['name(20)', 'id']]);
});

it('reports every difference between two descriptions as a readable line', function () {
    $left = SchemaReader::describe(READER_DB);
    $right = $left;
    $right['columns']['t'][2]['column_type'] = 'varchar(50)';
    unset($right['indexes']['t']['by_name']);
    $right['tables']['extra'] = ['engine' => 'InnoDB', 'collation' => 'x', 'auto_increment' => null];

    expect(SchemaReader::diff($left, $right))->toBe([
        'columns.t.2.column_type: expected "varchar(100)", got "varchar(50)"',
        'indexes.t.by_name: expected {"non_unique":0,"columns":["name(20)","id"]}, got <missing>',
        'tables.extra: expected <missing>, got {"engine":"InnoDB","collation":"x","auto_increment":null}',
    ]);
    expect(SchemaReader::diff($left, $left))->toBe([]);
});
