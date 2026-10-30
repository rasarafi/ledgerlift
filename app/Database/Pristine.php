<?php

namespace App\Database;

/**
 * The legacy database exactly as the container's installer left it.
 */
final class Pristine
{
    public const DATABASE = 'ledgerlift_pristine_test';

    /**
     * Creates `ledgerlift_pristine_test` as a copy of `$legacy` if it is missing, and
     * returns its name.
     *
     * Only valid while the legacy database is untouched since its install: no data has
     * changed and no login has failed (a failed login writes ip_login_log; sessions are
     * files, so a successful one writes nothing). The new app's container calls this on
     * its first start, once legacy has installed itself (`ledgerlift:demo-seed`). To
     * start over, reset the whole stack with `docker compose down -v`; a stale pristine
     * copy is replaced only when the database is dropped by hand.
     */
    public static function ensure(string $legacy): string
    {
        $exists = Mysql::connection()->scalar(
            'SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
            [self::DATABASE],
        );

        if ((int) $exists === 0) {
            DatabaseSnapshot::copy($legacy, self::DATABASE);
        }

        return self::DATABASE;
    }
}
