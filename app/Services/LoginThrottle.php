<?php

namespace App\Services;

use DateTime;
use Exception;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The login throttle of Sessions.php, kept in ip_login_log with the same keys and rules:
 * a row per email (locks at 10 failures, for 12 hours) and a row per client ip under
 * `login_ip:<sha256>` (20 failures per 15 minutes, both configurable).
 */
final class LoginThrottle
{
    private const ACCOUNT_MAX_FAILURES = 10;

    private const ACCOUNT_LOCK_SECONDS = 12 * 3600;

    /** Sessions::_is_ip_rate_limited_login() (Sessions.php:377). */
    public function ipLimited(string $ip): bool
    {
        $log = $this->lookup($this->ipKey($ip));

        return $log !== null
            && $log->log_count >= (int) config('ledgerlift.login_ip_max_attempts')
            && $this->withinWindow($log, (int) config('ledgerlift.login_ip_window_minutes') * 60);
    }

    /**
     * Sessions::_login_log_check() (Sessions.php:436) plus the `< 10` test in authenticate():
     * 10 failures lock the account until 12 hours after the last one, when the row is cleared.
     */
    public function accountLocked(string $email): bool
    {
        $log = $this->lookup($email);

        if ($log === null || $log->log_count < self::ACCOUNT_MAX_FAILURES) {
            return false;
        }

        if ($this->withinWindow($log, self::ACCOUNT_LOCK_SECONDS)) {
            return true;
        }

        $this->reset($email);

        return false;
    }

    /** Sessions::authenticate() lines 361-364: one failure on the account and one on the ip. */
    public function recordFailure(string $email, string $ip): void
    {
        $this->addFailure($email);

        // Sessions::_record_rate_limited_attempt() (Sessions.php:538): a stale ip row restarts first.
        $key = $this->ipKey($ip);
        $log = $this->lookup($key);

        if ($log !== null && ! $this->withinWindow($log, (int) config('ledgerlift.login_ip_window_minutes') * 60)) {
            $this->reset($key);
        }

        $this->addFailure($key);
    }

    public function recordSuccess(string $email, string $ip): void
    {
        $this->reset($email);
        $this->reset($this->ipKey($ip));
    }

    private function ipKey(string $ip): string
    {
        return 'login_ip:'.hash('sha256', $ip);
    }

    private function lookup(string $key): ?stdClass
    {
        return DB::table('ip_login_log')->where('login_name', $key)->first();
    }

    /** Sessions::_login_log_addfailure() (Sessions.php:618): atomic upsert, timestamp as date('c'). */
    private function addFailure(string $key): void
    {
        DB::statement(
            'INSERT INTO ip_login_log (login_name, log_count, log_create_timestamp)
             VALUES (?, 1, ?)
             ON DUPLICATE KEY UPDATE log_count = log_count + 1, log_create_timestamp = VALUES(log_create_timestamp)',
            [$key, date('c')],
        );
    }

    private function reset(string $key): void
    {
        DB::table('ip_login_log')->where('login_name', $key)->delete();
    }

    /** Sessions::_login_log_is_within_window() (Sessions.php:550). */
    private function withinWindow(stdClass $log, int $seconds): bool
    {
        try {
            $timestamp = new DateTime((string) $log->log_create_timestamp);
        } catch (Exception) {
            return false;
        }

        return $timestamp->getTimestamp() > time() - $seconds;
    }
}
