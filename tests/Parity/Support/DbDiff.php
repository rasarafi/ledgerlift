<?php

namespace Tests\Parity\Support;

use RuntimeException;
use Tests\Support\Mysql;

/**
 * Compares two databases row by row: every `ip_*` table except `ip_sessions` (unused by
 * both apps; legacy sessions are files), matched by primary key.
 */
final class DbDiff
{
    /** The `column` of an entry for a row that exists on one side only (the value is the row as JSON). */
    public const ROW = '(row)';

    /** The `column` of an entry for a table that exists on one side only. */
    public const TABLE = '(table)';

    private const IGNORED_TABLES = ['ip_sessions'];

    /**
     * @param  array<string, string>|null  $skip  `table.column => reason` to leave out; null means normalise.php.
     * @return list<array{table: string, pk: ?string, column: string, legacy: mixed, new: mixed}>
     */
    public static function compare(string $a, string $b, ?array $skip = null): array
    {
        $skip ??= require dirname(__DIR__).'/normalise.php';
        $tables = [self::tables($a), self::tables($b)];
        $diff = [];

        foreach (array_unique([...array_keys($tables[0]), ...array_keys($tables[1])]) as $table) {
            if (! isset($tables[0][$table], $tables[1][$table])) {
                $diff[] = self::entry($table, null, self::TABLE, isset($tables[0][$table]) ? 'exists' : null, isset($tables[1][$table]) ? 'exists' : null);

                continue;
            }

            $compared = fn (array $columns): array => array_values(array_filter($columns, fn (string $column): bool => ! isset($skip["$table.$column"])));
            $columnsA = $compared($tables[0][$table]['columns']);
            $columnsB = $compared($tables[1][$table]['columns']);

            foreach (array_diff($columnsA, $columnsB) as $column) {
                $diff[] = self::entry($table, null, $column, 'exists', null);
            }
            foreach (array_diff($columnsB, $columnsA) as $column) {
                $diff[] = self::entry($table, null, $column, null, 'exists');
            }

            $pk = $tables[0][$table]['pk'];

            if ($pk === []) {
                throw new RuntimeException("$table has no primary key to match rows by.");
            }

            array_push($diff, ...self::compareRows($table, $pk, array_intersect($columnsA, $columnsB), self::rows($a, $table, $pk), self::rows($b, $table, $pk)));
        }

        return $diff;
    }

    /**
     * The diff as a fixed-width table, for failure messages.
     *
     * @param  list<array{table: string, pk: ?string, column: string, legacy: mixed, new: mixed}>  $diff
     */
    public static function format(array $diff): string
    {
        $cell = function (mixed $value): string {
            $text = $value === null ? 'NULL' : (is_string($value) ? $value : json_encode($value));

            return mb_strimwidth(str_replace("\n", '\n', (string) $text), 0, 60, '...');
        };

        $rows = [['table', 'pk', 'column', 'legacy', 'new'], ...array_map(fn (array $entry) => array_map($cell, array_values($entry)), $diff)];
        $widths = array_map(fn (int $i) => max(array_map(fn (array $row) => mb_strlen($row[$i]), $rows)), range(0, 4));

        return implode("\n", array_map(
            fn (array $row) => implode(' | ', array_map(fn (string $text, int $width) => $text.str_repeat(' ', $width - mb_strlen($text)), $row, $widths)),
            $rows,
        ));
    }

    /**
     * @param  list<string>  $pk
     * @param  array<int, string>  $columns
     * @param  array<string, array<string, mixed>>  $rowsA
     * @param  array<string, array<string, mixed>>  $rowsB
     * @return list<array{table: string, pk: ?string, column: string, legacy: mixed, new: mixed}>
     */
    private static function compareRows(string $table, array $pk, array $columns, array $rowsA, array $rowsB): array
    {
        $keys = array_unique([...array_keys($rowsA), ...array_keys($rowsB)]);
        // Keys are the primary key values joined by commas; order them as the values, not as text.
        usort($keys, fn (string $x, string $y) => self::keyValues($x, $rowsA, $rowsB, $pk) <=> self::keyValues($y, $rowsA, $rowsB, $pk));

        $diff = [];
        $json = fn (array $row): string => (string) json_encode(array_intersect_key($row, array_flip($columns)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($keys as $key) {
            if (! isset($rowsA[$key], $rowsB[$key])) {
                $diff[] = self::entry($table, (string) $key, self::ROW, isset($rowsA[$key]) ? $json($rowsA[$key]) : null, isset($rowsB[$key]) ? $json($rowsB[$key]) : null);

                continue;
            }

            foreach ($columns as $column) {
                if ($rowsA[$key][$column] !== $rowsB[$key][$column]) {
                    $diff[] = self::entry($table, (string) $key, $column, $rowsA[$key][$column], $rowsB[$key][$column]);
                }
            }
        }

        return $diff;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rowsA
     * @param  array<string, array<string, mixed>>  $rowsB
     * @param  list<string>  $pk
     * @return list<mixed>
     */
    private static function keyValues(string $key, array $rowsA, array $rowsB, array $pk): array
    {
        $row = $rowsA[$key] ?? $rowsB[$key];

        return array_map(fn (string $column) => $row[$column], $pk);
    }

    /**
     * Every compared table with its columns (in table order) and primary key columns.
     *
     * @return array<string, array{columns: list<string>, pk: list<string>}>
     */
    private static function tables(string $database): array
    {
        $columns = Mysql::connection()->select(
            "SELECT c.TABLE_NAME AS table_name, c.COLUMN_NAME AS column_name, c.COLUMN_KEY = 'PRI' AS is_pk
             FROM information_schema.COLUMNS c
             JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME AND t.TABLE_TYPE = 'BASE TABLE'
             WHERE c.TABLE_SCHEMA = ? AND c.TABLE_NAME LIKE 'ip\\_%'
             ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION",
            [$database],
        );

        $tables = [];

        foreach ($columns as $column) {
            if (in_array($column->table_name, self::IGNORED_TABLES, true)) {
                continue;
            }

            $tables[$column->table_name]['columns'][] = $column->column_name;
            $tables[$column->table_name]['pk'] ??= [];

            if ($column->is_pk) {
                $tables[$column->table_name]['pk'][] = $column->column_name;
            }
        }

        return $tables;
    }

    /**
     * The table's rows keyed by their primary key values joined by commas.
     *
     * @param  list<string>  $pk
     * @return array<string, array<string, mixed>>
     */
    private static function rows(string $database, string $table, array $pk): array
    {
        $rows = [];

        foreach (Mysql::connection()->table("$database.$table")->get() as $row) {
            $row = (array) $row;
            $rows[implode(',', array_map(fn (string $column) => (string) $row[$column], $pk))] = $row;
        }

        return $rows;
    }

    /**
     * @return array{table: string, pk: ?string, column: string, legacy: mixed, new: mixed}
     */
    private static function entry(string $table, ?string $pk, string $column, mixed $legacy, mixed $new): array
    {
        return ['table' => $table, 'pk' => $pk, 'column' => $column, 'legacy' => $legacy, 'new' => $new];
    }
}
