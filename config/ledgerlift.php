<?php

use App\Support\LegacyEnv;

return [

    // Modules already ported to this app. Navbar links to any other module go to the legacy app.
    'migrated_modules' => [
        'dashboard', 'sessions', 'clients', 'products', 'families', 'units', 'tax_rates',
        'invoice_groups', 'invoices', 'payments', 'payment_methods', 'filter',
    ],

    'legacy_url' => env('LEGACY_URL', 'http://localhost:8091'),

    // The legacy app's database (LEGACY_DB), which `ledgerlift:demo-seed` snapshots and seeds.
    'legacy_database' => env('LEGACY_DB'),

    // config.php: ENABLE_INVOICE_DELETION (invoices other than drafts may be deleted) and
    // DISABLE_READ_ONLY (invoices are never read-only), read with env_bool(): only "true" is true.
    'enable_invoice_deletion' => LegacyEnv::bool('ENABLE_INVOICE_DELETION'),
    'disable_read_only' => LegacyEnv::bool('DISABLE_READ_ONLY'),

    // ENCRYPTION_KEY of the legacy install (libraries/Crypt.php): invoice PDF passwords are
    // encrypted with it, so both apps need the same key to read each other's.
    'encryption_key' => LegacyEnv::string('ENCRYPTION_KEY'),

    // Admin_Controller::setCacheHeaders(): X_FRAME_OPTIONS.
    'x_frame_options' => env('X_FRAME_OPTIONS', 'SAMEORIGIN'),

    // Sessions::_is_ip_rate_limited_login(): LOGIN_IP_MAX_ATTEMPTS / LOGIN_IP_WINDOW_MINUTES.
    'login_ip_max_attempts' => (int) env('LOGIN_IP_MAX_ATTEMPTS', 20),
    'login_ip_window_minutes' => (int) env('LOGIN_IP_WINDOW_MINUTES', 15),

];
