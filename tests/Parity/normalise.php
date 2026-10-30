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
    'ip_clients.client_date_created' => 'Wall-clock time of the save: date(\'Y-m-d H:i:s\') in MY_Model::save() (MY_Model.php, `$datetime`).',
    'ip_clients.client_date_modified' => 'Same date(\'Y-m-d H:i:s\') of MY_Model::save(), written on create and on every update.',
];
