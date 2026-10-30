<?php

namespace App\Console\Commands;

use App\Database\DatabaseSnapshot;
use App\Database\DemoCompany;
use App\Database\Mysql;
use App\Database\Pristine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Gives a fresh stack the same demo data in both apps. The new app's container runs it on
 * every start (docker/entrypoint.d/60-ledgerlift-demo-seed.sh), after legacy has installed
 * itself; it does nothing once the new database has a user.
 */
class DemoSeed extends Command
{
    protected $signature = 'ledgerlift:demo-seed';

    protected $description = 'Copy the pristine legacy install into the new database and load the demo company into both (first start only)';

    public function handle(): int
    {
        $new = (string) config('database.connections.'.config('database.default').'.database');
        $legacy = (string) config('ledgerlift.legacy_database');

        try {
            DatabaseSnapshot::ensureTestDatabase($new);
            DatabaseSnapshot::ensureTestDatabase($legacy);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage().' Set DB_DATABASE and LEGACY_DB to databases whose names end in _test.');

            return self::FAILURE;
        }

        if ($this->hasRows($new, 'ip_users')) {
            $this->info("$new already has users; nothing to seed.");

            return self::SUCCESS;
        }

        DatabaseSnapshot::copy(Pristine::ensure($legacy), $new);
        // The copy drops and recreates the database the default connection had selected.
        DB::purge();
        DemoCompany::load($new);
        $this->info("$new: the pristine legacy install plus the demo company.");

        if ($this->hasRows($legacy, 'ip_clients')) {
            $this->warn("$legacy already has clients; left as it is.");
        } else {
            DemoCompany::load($legacy);
            $this->info("$legacy: the demo company added.");
        }

        return self::SUCCESS;
    }

    private function hasRows(string $database, string $table): bool
    {
        $db = Mysql::connection();

        $exists = (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$database, $table],
        );

        return $exists > 0 && (bool) $db->scalar("SELECT EXISTS (SELECT 1 FROM `$database`.`$table`)");
    }
}
