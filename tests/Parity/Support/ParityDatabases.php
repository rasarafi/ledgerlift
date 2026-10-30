<?php

namespace Tests\Parity\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatabaseSnapshot;
use Tests\Support\Mysql;
use Tests\Support\Pristine;

/**
 * The two apps' databases (LEGACY_DB and NEW_DB), reset to the same baseline before each
 * scenario: the pristine legacy install plus fixtures/company.sql.
 */
final class ParityDatabases
{
    public const BASELINE = 'ledgerlift_baseline_test';

    /** Pest runs every test file in one process, so the baseline is built once per run. */
    private static bool $built = false;

    /**
     * Restores both databases to the baseline, then applies `ip_settings` overrides to both:
     * an existing key's rows are updated, a missing key is inserted.
     *
     * @param  array<string, string>  $settings
     */
    public static function reset(array $settings = []): void
    {
        if (! self::$built) {
            DatabaseSnapshot::copy(Pristine::ensure(), self::BASELINE);
            self::loadFixture(self::BASELINE, dirname(__DIR__).'/fixtures/company.sql');
            self::$built = true;
        }

        foreach (['legacy', 'new'] as $side) {
            DatabaseSnapshot::restore(self::BASELINE, self::database($side));
            // A restore that falls back to a full copy drops the database this connection had selected.
            DB::purge(Mysql::name(self::database($side)));
            $db = self::connection($side);

            foreach ($settings as $key => $value) {
                // ip_settings has no unique key, so update every row of a key (updateOrInsert updates one).
                if ($db->table('ip_settings')->where('setting_key', $key)->exists()) {
                    $db->table('ip_settings')->where('setting_key', $key)->update(['setting_value' => $value]);
                } else {
                    $db->table('ip_settings')->insert(['setting_key' => $key, 'setting_value' => $value]);
                }
            }
        }
    }

    /** The database name of `legacy` (LEGACY_DB) or `new` (NEW_DB). */
    public static function database(string $side): string
    {
        return (string) env($side === 'legacy' ? 'LEGACY_DB' : 'NEW_DB');
    }

    public static function connection(string $side): Connection
    {
        return Mysql::connection(self::database($side));
    }

    /** Runs a fixture file one statement at a time, so an error in any statement surfaces. */
    private static function loadFixture(string $database, string $path): void
    {
        $db = Mysql::connection($database);
        $sql = (string) preg_replace('/^--.*$/m', '', (string) file_get_contents($path));

        foreach (preg_split('/;\s*$/m', $sql) ?: [] as $statement) {
            if (trim($statement) !== '') {
                $db->statement($statement);
            }
        }
    }
}
