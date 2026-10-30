<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Read access to ip_settings, as Mdl_settings::setting() does in the legacy app.
 *
 * Every row is loaded once, on first use. An empty string counts as unset.
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
        /** @var array<string, string> */
        return Setting::query()->orderBy('setting_id')->pluck('setting_value', 'setting_key')->all();
    }
}
