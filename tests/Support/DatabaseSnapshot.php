<?php

namespace Tests\Support;

use InvalidArgumentException;

/**
 * Copies one `*_test` database over another, table by table.
 *
 * InvoicePlane's tables are MyISAM, so there is no transaction to roll back; tests
 * restore a known state by copying a baseline back instead.
 */
final class DatabaseSnapshot
{
    /**
     * Drops and recreates `$to` (same charset and collation as `$from`), then copies
     * every table: structure, rows and the AUTO_INCREMENT counter.
     */
    public static function copy(string $from, string $to): void
    {
        foreach ([$from, $to] as $name) {
            if (! preg_match('/^[A-Za-z0-9_]+_test$/D', $name)) {
                throw new InvalidArgumentException("Refusing to copy: '$name' is not a database whose name ends in _test.");
            }
        }

        if ($from === $to) {
            throw new InvalidArgumentException('Refusing to copy a database onto itself.');
        }

        $db = Mysql::connection();
        // MySQL 8 serves information_schema statistics (AUTO_INCREMENT) from a cache.
        $db->statement('SET SESSION information_schema_stats_expiry = 0');

        $charset = $db->selectOne(
            'SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$from],
        );

        if ($charset === null) {
            throw new InvalidArgumentException("Database '$from' does not exist.");
        }

        $tables = $db->select(
            "SELECT TABLE_NAME AS name, AUTO_INCREMENT AS next FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'",
            [$from],
        );

        $db->statement("DROP DATABASE IF EXISTS `$to`");
        $db->statement("CREATE DATABASE `$to` CHARACTER SET {$charset->charset} COLLATE {$charset->collation}");

        foreach ($tables as $table) {
            $db->statement("CREATE TABLE `$to`.`{$table->name}` LIKE `$from`.`{$table->name}`");
            $db->statement("INSERT INTO `$to`.`{$table->name}` SELECT * FROM `$from`.`{$table->name}`");
        }

        // CREATE TABLE ... LIKE does not carry the counter, and it may be ahead of MAX(id).
        // INSERT ... SELECT already leaves most tables right; an ALTER rebuilds the table, so
        // only the ones that differ get one.
        $copied = $db->select(
            "SELECT TABLE_NAME AS name, AUTO_INCREMENT AS next FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'",
            [$to],
        );
        $copiedNext = array_column($copied, 'next', 'name');

        foreach ($tables as $table) {
            if ($table->next !== null && (int) $copiedNext[$table->name] !== (int) $table->next) {
                $db->statement("ALTER TABLE `$to`.`{$table->name}` AUTO_INCREMENT = ".(int) $table->next);
            }
        }
    }
}
