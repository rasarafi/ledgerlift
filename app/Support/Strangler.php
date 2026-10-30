<?php

namespace App\Support;

/**
 * Strangler-fig routing for links: a module already ported to this app stays here,
 * any other goes to the legacy app.
 */
final class Strangler
{
    public static function url(string $path): string
    {
        $path = ltrim($path, '/');
        $module = (preg_split('#[/?\#]#', $path) ?: [''])[0];

        if (in_array($module, config('ledgerlift.migrated_modules'), true)) {
            return url($path);
        }

        return self::legacy($path);
    }

    /**
     * The same path on the legacy app, for screens that are not ported even though their
     * module is (sessions/passwordreset).
     */
    public static function legacy(string $path): string
    {
        return rtrim((string) config('ledgerlift.legacy_url'), '/').'/'.ltrim($path, '/');
    }
}
