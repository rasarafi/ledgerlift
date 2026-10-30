<?php

namespace App\Database;

/**
 * The made-up demo company (database/fixtures/company.sql): tax rates, product families and
 * units, products and clients with fixed ids, loaded on top of a pristine legacy install. The
 * parity suite's baseline is built from it, and `ledgerlift:demo-seed` loads it into both apps.
 */
final class DemoCompany
{
    /** Runs the fixture one statement at a time, so an error in any statement surfaces. */
    public static function load(string $database): void
    {
        DatabaseSnapshot::ensureTestDatabase($database);

        $db = Mysql::connection($database);
        $sql = (string) preg_replace('/^--.*$/m', '', (string) file_get_contents(database_path('fixtures/company.sql')));

        foreach (preg_split('/;\s*$/m', $sql) ?: [] as $statement) {
            if (trim($statement) !== '') {
                $db->statement($statement);
            }
        }
    }
}
