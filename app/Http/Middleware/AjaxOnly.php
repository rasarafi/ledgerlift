<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Base_Controller::__construct(): an AJAX controller exits with an empty page unless the
 * request carries `X-Requested-With: XMLHttpRequest`. It runs before the login check.
 */
class AjaxOnly
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if (strtolower((string) $request->header('X-Requested-With')) !== 'xmlhttprequest') {
            return new Response('');
        }

        return $next($request);
    }
}
