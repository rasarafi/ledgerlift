<?php

namespace App\Support;

/** helpers/security_helper.php: get_safe_referer() and escape_url_for_javascript() (CWE-601). */
final class SafeReferer
{
    /**
     * get_safe_referer() (security_helper.php:48-98): the referer when it is on this app's own scheme, host and
     * port and has no control characters or angle brackets, else the default.
     */
    public static function url(?string $referer, string $default): string
    {
        $default = $default === '' ? url('/') : $default;

        if (empty($referer)) {
            return $default;
        }

        if (preg_match('/[\x00-\x1F\x7F<>]/', $referer)) {
            return $default;
        }

        $base = parse_url(url('/'));
        $parts = parse_url($referer);

        if ($base === false || $parts === false || empty($base['host']) || empty($parts['host'])) {
            return $default;
        }

        $baseScheme = $base['scheme'] ?? 'http';
        $scheme = $parts['scheme'] ?? 'http';
        $basePort = $base['port'] ?? ($baseScheme === 'https' ? 443 : 80);
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        return $base['host'] === $parts['host'] && $baseScheme === $scheme && $basePort === $port ? $referer : $default;
    }

    /** escape_url_for_javascript() (:180-187): the checked URL as a JavaScript string literal. */
    public static function forJavascript(string $url): string
    {
        return (string) json_encode(self::url($url, url('/')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
