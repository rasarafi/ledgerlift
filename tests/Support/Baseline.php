<?php

namespace Tests\Support;

use App\Database\DatabaseSnapshot;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * The known database state every Feature test starts from: a freshly migrated
 * `ledgerlift_test` plus the admin user, kept as a snapshot and restored before
 * each test (MyISAM cannot roll back).
 */
final class Baseline
{
    public const SNAPSHOT = 'ledgerlift_snapshot_test';

    public const WORKING = 'ledgerlift_test';

    /** Pest runs every test file in one process, so this builds the snapshot once per run. */
    private static bool $built = false;

    public static function restore(): void
    {
        if (! self::$built) {
            self::build();
            self::$built = true;
        }

        DatabaseSnapshot::restore(self::SNAPSHOT, self::WORKING);

        // When restore() falls back to a full copy, it drops the database the app's connection had selected.
        DB::purge();
    }

    private static function build(): void
    {
        Artisan::call('migrate:fresh');

        DB::table('ip_users')->insert([
            'user_type' => 1,
            'user_active' => 1,
            'user_date_created' => now(),
            'user_date_modified' => now(),
            'user_name' => 'Admin',
            'user_email' => 'admin@ledgerlift.test',
            'user_password' => password_hash('ledgerlift-demo-1', PASSWORD_BCRYPT),
            // Any non-empty value makes legacy skip its pre-1.2.0 md5 path (Mdl_sessions.php:40).
            'user_psalt' => bin2hex(random_bytes(11)),
        ]);

        DatabaseSnapshot::copy(self::WORKING, self::SNAPSHOT);
    }
}
