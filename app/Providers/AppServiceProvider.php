<?php

namespace App\Providers;

use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(Settings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Destructive artisan commands (migrate:fresh, db:wipe, ...) run only
        // against a database whose name ends in `_test`. Keyed on the database
        // name, not APP_ENV: `--env=testing` silently falls back to .env when no
        // .env.testing exists.
        $database = (string) config('database.connections.'.config('database.default').'.database');
        DB::prohibitDestructiveCommands(! str_ends_with($database, '_test'));
    }
}
