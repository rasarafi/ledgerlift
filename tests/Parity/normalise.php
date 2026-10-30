<?php

/*
 * Columns DbDiff leaves out because they differ between two identical runs of the same
 * scenario against the same app: time-dependent or random values, never behaviour.
 * Add a column only once two identical legacy runs show it differing (the "two identical
 * legacy runs" self-check in HarnessSelfCheckTest does this for the login scenarios).
 *
 * `table.column => reason`. ip_sessions is skipped as a whole by DbDiff.
 */

return [
    'ip_login_log.log_create_timestamp' => 'Wall-clock time of the last failed login (date(\'c\'), Sessions.php:624).',
];
