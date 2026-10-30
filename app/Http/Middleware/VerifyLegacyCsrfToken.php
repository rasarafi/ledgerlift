<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Laravel's CSRF check, speaking the legacy protocol: the token travels in the `_ip_csrf`
 * field (scripts.js appends it to every jQuery POST) and the current token comes back in
 * an `X-_ip_csrf` header, which scripts.js copies into the csrf_token_value meta tag.
 * The token itself is Laravel's per-session token; it does not rotate per request
 * (it is regenerated on login and logout).
 */
class VerifyLegacyCsrfToken extends PreventRequestForgery
{
    public const FIELD = '_ip_csrf';

    public function handle($request, Closure $next)
    {
        try {
            $response = parent::handle($request, $next);
        } catch (TokenMismatchException) {
            // CodeIgniter answers a failed check with 403, not Laravel's 419.
            throw new HttpException(403, 'The action you have requested is not allowed.');
        }

        $response->headers->set('X-'.self::FIELD, (string) $request->session()->token());

        return $response;
    }

    protected function getTokenFromRequest($request)
    {
        $token = $request->input(self::FIELD);

        return is_string($token) && $token !== '' ? $token : parent::getTokenFromRequest($request);
    }
}
