<?php

namespace App\Database;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Connections to the compose MySQL that are independent of the app's default
 * connection, so the demo seed and the tests can read or rebuild any `*_test`
 * database by name.
 */
final class Mysql
{
    /**
     * A connection with no schema selected (or the given one). Not strict, like the
     * server itself: InvoicePlane writes '0000-00-00' and copies must carry it.
     */
    public static function connection(?string $database = null): Connection
    {
        $name = self::name($database);

        if (config("database.connections.$name") === null) {
            config(["database.connections.$name" => [
                ...config('database.connections.mysql'),
                'database' => $database,
                'strict' => false,
            ]]);
        }

        return DB::connection($name);
    }

    public static function name(?string $database = null): string
    {
        return 'mysql_'.($database ?? 'admin');
    }
}
