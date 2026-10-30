<?php

namespace App\Support;

use Illuminate\Support\Env;

final class LegacyEnv
{
    /**
     * index.php env_bool(): `env($key, 'false') === 'true'`. Only the exact string `true` is
     * true; Laravel's env() would also take "TRUE" and "(true)".
     */
    public static function bool(string $key): bool
    {
        return Env::getRepository()->get($key) === 'true';
    }

    /** The raw value, as legacy's getenv() reads it (no "null"/"empty" conversion), or '' when unset. */
    public static function string(string $key): string
    {
        return (string) Env::getRepository()->get($key);
    }
}
