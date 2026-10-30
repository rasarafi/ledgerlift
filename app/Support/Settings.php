<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Read access to ip_settings, as Mdl_settings::setting() does in the legacy app.
 *
 * Every row is loaded once, on first use, along with `current_version`. An empty string
 * counts as unset.
 */
final class Settings
{
    /** @var array<string, string>|null */
    private ?array $values;

    /**
     * @param  array<string, string>|null  $values  Preloaded values; null reads them from ip_settings.
     */
    public function __construct(?array $values = null)
    {
        $this->values = $values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->values ??= $this->load();

        $value = $this->values[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    /**
     * @return array<string, string>
     */
    private function load(): array
    {
        // ip_settings has no unique index on setting_key. Legacy overwrites in
        // row order (Mdl_settings::load_settings), so the highest setting_id wins.
        /** @var array<string, string> $values */
        $values = Setting::query()->orderBy('setting_id')->pluck('setting_value', 'setting_key')->all();

        // Mdl_settings::load_settings() appends the version of the last applied migration.
        $values['current_version'] = $this->currentVersion();

        return $values;
    }

    /**
     * Mdl_versions::get_current_version(): the newest ip_versions row (by date applied, then
     * file name), as "043_1.7.2.sql" -> "1.7.2". Empty when there are no rows.
     */
    private function currentVersion(): string
    {
        $file = DB::table('ip_versions')
            ->orderByDesc('version_date_applied')
            ->orderByDesc('version_file')
            ->value('version_file');

        if (! is_string($file)) {
            return '';
        }

        return str_replace('.sql', '', mb_substr($file, (int) mb_strpos($file, '_') + 1));
    }
}
