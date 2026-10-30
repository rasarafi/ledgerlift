<?php

namespace App\Database;

use Illuminate\Database\Connection;
use InvalidArgumentException;

/**
 * Copies one `*_test` database over another, table by table.
 *
 * InvoicePlane's tables are MyISAM, so there is no transaction to roll back; tests
 * restore a known state by copying a baseline back instead, and `ledgerlift:demo-seed`
 * copies the pristine legacy install into the new app's database.
 */
final class DatabaseSnapshot
{
    /**
     * Drops and recreates `$to` (same charset and collation as `$from`), then copies
     * every table: structure, rows and the AUTO_INCREMENT counter.
     */
    public static function copy(string $from, string $to): void
    {
        $db = self::connect($from, $to);

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
        self::restoreCounters($db, $tables, $to);
    }

    /**
     * Makes `$to` equal to `$from`, like copy(), but in place when it can: when `$to`
     * already has the same tables with the same definitions, each one is emptied and
     * refilled instead of the whole database being dropped and recreated. Anything
     * else (a missing target, an extra or missing table, a changed column, an empty or
     * missing source) falls back to copy().
     */
    public static function restore(string $from, string $to): void
    {
        $db = self::connect($from, $to);

        $definitions = self::definitions($db, $from);

        if ($definitions === [] || $definitions !== self::definitions($db, $to)) {
            self::copy($from, $to);

            return;
        }

        $tables = $db->select(
            "SELECT TABLE_NAME AS name, AUTO_INCREMENT AS next FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'",
            [$from],
        );

        foreach ($tables as $table) {
            // DELETE rather than TRUNCATE: on MyISAM, TRUNCATE recreates the table files and
            // costs about ten times as much. DELETE keeps the counter, which is fixed below.
            $db->statement("DELETE FROM `$to`.`{$table->name}`");
            $db->statement("INSERT INTO `$to`.`{$table->name}` SELECT * FROM `$from`.`{$table->name}`");
        }

        self::restoreCounters($db, $tables, $to);
    }

    /** Refuses any database whose name does not end in `_test`. */
    public static function ensureTestDatabase(string $name): void
    {
        if (! preg_match('/^[A-Za-z0-9_]+_test$/D', $name)) {
            throw new InvalidArgumentException("Refusing to write: '$name' is not a database whose name ends in _test.");
        }
    }

    /** Checks both names, and returns an admin connection with uncached table statistics. */
    private static function connect(string $from, string $to): Connection
    {
        self::ensureTestDatabase($from);
        self::ensureTestDatabase($to);

        if ($from === $to) {
            throw new InvalidArgumentException('Refusing to copy a database onto itself.');
        }

        $db = Mysql::connection();
        // MySQL 8 serves information_schema statistics (AUTO_INCREMENT) from a cache.
        $db->statement('SET SESSION information_schema_stats_expiry = 0');

        return $db;
    }

    /**
     * Each base table's `SHOW CREATE TABLE`, without the AUTO_INCREMENT counter (rows decide
     * that), keyed by table name.
     *
     * @return array<string, string>
     */
    private static function definitions(Connection $db, string $database): array
    {
        $names = array_column($db->select(
            "SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME",
            [$database],
        ), 'name');

        $definitions = [];

        foreach ($names as $name) {
            $create = (array) $db->selectOne("SHOW CREATE TABLE `$database`.`$name`");
            $definitions[$name] = (string) preg_replace('/ AUTO_INCREMENT=\d+/', '', (string) $create['Create Table']);
        }

        return $definitions;
    }

    /**
     * Sets each table's counter to the source's. INSERT ... SELECT already leaves most tables
     * right; an ALTER rebuilds the table, so only the ones that differ get one.
     *
     * @param  array<int, object{name: string, next: int|string|null}>  $tables  The source's tables.
     */
    private static function restoreCounters(Connection $db, array $tables, string $to): void
    {
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
