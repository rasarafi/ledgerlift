<?php

namespace Tests\Support;

/**
 * Reads a database's structure from information_schema, in a shape two databases
 * can be compared by: tables, columns in ordinal order, and indexes.
 *
 * @phpstan-type Description array{
 *     tables: array<string, array{engine: string|null, collation: string|null, auto_increment: int|null}>,
 *     columns: array<string, array<int, array<string, mixed>>>,
 *     indexes: array<string, array<string, array{non_unique: int, columns: list<string>}>>
 * }
 */
final class SchemaReader
{
    /**
     * @return Description
     */
    public static function describe(string $database): array
    {
        $db = Mysql::connection();
        // MySQL 8 serves information_schema statistics (AUTO_INCREMENT) from a cache that
        // can be stale right after DDL or inserts; expiry 0 reads the live value.
        $db->statement('SET SESSION information_schema_stats_expiry = 0');

        $description = ['tables' => [], 'columns' => [], 'indexes' => []];

        foreach ($db->select(
            "SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_COLLATION AS collation, AUTO_INCREMENT AS next
             FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME",
            [$database],
        ) as $row) {
            $description['tables'][$row->name] = [
                'engine' => $row->engine,
                'collation' => $row->collation,
                'auto_increment' => $row->next === null ? null : (int) $row->next,
            ];
        }

        foreach ($db->select(
            'SELECT TABLE_NAME AS t, ORDINAL_POSITION AS pos, COLUMN_NAME AS name, COLUMN_TYPE AS column_type,
                    IS_NULLABLE AS is_nullable, COLUMN_DEFAULT AS column_default, EXTRA AS extra,
                    CHARACTER_SET_NAME AS charset, COLLATION_NAME AS collation
             FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION',
            [$database],
        ) as $row) {
            $description['columns'][$row->t][(int) $row->pos] = [
                'name' => $row->name,
                'column_type' => $row->column_type,
                'is_nullable' => $row->is_nullable,
                'column_default' => $row->column_default,
                'extra' => $row->extra,
                'charset' => $row->charset,
                'collation' => $row->collation,
            ];
        }

        foreach ($db->select(
            'SELECT TABLE_NAME AS t, INDEX_NAME AS name, NON_UNIQUE AS non_unique, COLUMN_NAME AS col, SUB_PART AS sub_part
             FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX',
            [$database],
        ) as $row) {
            $description['indexes'][$row->t][$row->name]['non_unique'] = (int) $row->non_unique;
            $description['indexes'][$row->t][$row->name]['columns'][] = $row->col.($row->sub_part === null ? '' : "($row->sub_part)");
        }

        return $description;
    }

    /**
     * One readable line per difference, `path: expected X, got Y`. Empty when equal.
     *
     * @param  Description  $expected
     * @param  Description  $actual
     * @return list<string>
     */
    public static function diff(array $expected, array $actual): array
    {
        $left = self::flatten($expected);
        $right = self::flatten($actual);
        $lines = [];

        foreach (array_keys($left + $right) as $path) {
            $want = array_key_exists($path, $left) ? self::show($left[$path]) : '<missing>';
            $got = array_key_exists($path, $right) ? self::show($right[$path]) : '<missing>';

            if ($want !== $got) {
                $lines[] = "$path: expected $want, got $got";
            }
        }

        sort($lines);

        return $lines;
    }

    /**
     * Paths stop at a column attribute, an index or a table, so one line names one thing.
     *
     * @param  Description  $description
     * @return array<string, mixed>
     */
    private static function flatten(array $description): array
    {
        $flat = [];

        foreach ($description['tables'] as $table => $row) {
            $flat["tables.$table"] = $row;
        }

        foreach ($description['columns'] as $table => $columns) {
            foreach ($columns as $position => $column) {
                foreach ($column as $attribute => $value) {
                    $flat["columns.$table.$position.$attribute"] = $value;
                }
            }
        }

        foreach ($description['indexes'] as $table => $indexes) {
            foreach ($indexes as $index => $row) {
                $flat["indexes.$table.$index"] = $row;
            }
        }

        return $flat;
    }

    private static function show(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
