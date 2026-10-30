<?php

return [

    // Modules already ported to this app. Navbar links to any other module go to the legacy app.
    'migrated_modules' => [
        'dashboard', 'sessions', 'clients', 'products', 'families', 'units', 'tax_rates',
        'invoice_groups', 'invoices', 'payments', 'payment_methods', 'filter',
    ],

    'legacy_url' => env('LEGACY_URL', 'http://localhost:8091'),

    // config.php: ENABLE_INVOICE_DELETION (invoices other than drafts may be deleted) and
    // DISABLE_READ_ONLY (invoices are never read-only).
    'enable_invoice_deletion' => (bool) env('ENABLE_INVOICE_DELETION', false),
    'disable_read_only' => (bool) env('DISABLE_READ_ONLY', false),

    // config.php: ENABLE_INVOICE_DELETION (invoices other than drafts may be deleted) and
    // DISABLE_READ_ONLY (invoices are never read-only).
    'enable_invoice_deletion' => (bool) env('ENABLE_INVOICE_DELETION', false),
    'disable_read_only' => (bool) env('DISABLE_READ_ONLY', false),

    // Admin_Controller::setCacheHeaders(): X_FRAME_OPTIONS.
    'x_frame_options' => env('X_FRAME_OPTIONS', 'SAMEORIGIN'),

    // Sessions::_is_ip_rate_limited_login(): LOGIN_IP_MAX_ATTEMPTS / LOGIN_IP_WINDOW_MINUTES.
    'login_ip_max_attempts' => (int) env('LOGIN_IP_MAX_ATTEMPTS', 20),
    'login_ip_window_minutes' => (int) env('LOGIN_IP_WINDOW_MINUTES', 15),

];
