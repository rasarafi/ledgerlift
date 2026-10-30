<?php

/*
 * Columns whose values differ between two identical runs of the same scenario against the
 * same app: time-dependent or random values, never behaviour. DbDiff does not compare their
 * values, only that both sides agree on NULL and, for date columns, on holding a real date
 * (a zero date shows up as itself); every other value reads as DbDiff::NORMALISED.
 * Add a column only once two identical legacy runs show it differing (the "two identical
 * legacy runs" self-check in HarnessSelfCheckTest does this for the login scenarios).
 *
 * `table.column => reason`. ip_sessions is skipped as a whole by DbDiff.
 */

return [
    'ip_login_log.log_create_timestamp' => 'Wall-clock time of the last failed login (date(\'c\'), Sessions.php:624).',
    'ip_clients.client_date_created' => 'Wall-clock time of the save: `$datetime = date(\'Y-m-d H:i:s\')` in MY_Model::save() (MY_Model.php:218), written to Mdl_clients::$date_created_field (Mdl_clients.php:23) on insert.',
    'ip_clients.client_date_modified' => 'Same `$datetime` of MY_Model::save() (MY_Model.php:218), written to Mdl_clients::$date_modified_field (Mdl_clients.php:25) on create (MY_Model.php:226) and on every update (MY_Model.php:250).',
    'ip_invoices.invoice_date_modified' => 'Same date(\'Y-m-d H:i:s\') of MY_Model::save(), Mdl_invoices::$date_modified_field (Mdl_invoices.php:25), on every invoice save.',
    'ip_invoice_items.item_date_added' => 'Wall-clock day of the item\'s insert: MY_Model::save() stamps date(\'Y-m-d H:i:s\') into Mdl_items::$date_created_field (Mdl_items.php:23), a DATE column; a run crossing midnight between the two halves would differ.',
    'ip_invoices.invoice_url_key' => 'Random: Mdl_invoices::get_url_key() is generate_secure_token(16), 32 hex characters of random_bytes() (Mdl_invoices.php:506-512).',
];
