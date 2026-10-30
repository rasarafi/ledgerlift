<?php

namespace Tests\Support;

use RuntimeException;

/**
 * The legacy database exactly as the container's installer left it.
 */
final class Pristine
{
    public const DATABASE = 'ledgerlift_pristine_test';

    /**
     * Creates `ledgerlift_pristine_test` from the database named by LEGACY_DB if it is
     * missing, and returns its name.
     *
     * Only valid while the legacy database is untouched since its install: nobody has
     * logged in (that writes ip_login_log) or changed data. To start over, reset the
     * whole stack with `docker compose down -v`, then bring it up again; a stale
     * pristine copy is replaced only when the database is dropped by hand.
     */
    public static function ensure(): string
    {
        $legacy = env('LEGACY_DB');

        if (! is_string($legacy) || $legacy === '') {
            throw new RuntimeException('LEGACY_DB must name the freshly installed legacy database.');
        }

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
