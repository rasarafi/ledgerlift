<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base_Controller::__construct(): any URL containing "delete" answers 404 unless it is a POST.
 * Legacy tests the whole current_url() (host included), so this does too.
 */
class RejectDeleteOverGet
{
    public function handle(Request $request, Closure $next): Response
    {
        if (str_contains($request->url(), 'delete') && ! $request->isMethod('POST')) {
            abort(404);
        }

        return $next($request);
    }
}
