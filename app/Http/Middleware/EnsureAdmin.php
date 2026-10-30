<?php

namespace App\Http\Middleware;

use App\Http\Responses\LegacyRedirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin_Controller / User_Controller::revalidate_user_type(): every request re-reads the
 * user row (the guard loads it from ip_users, nothing is cached in the session) and drops
 * the whole session unless the user still exists, is active and is an admin (user_type 1).
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user === null || (int) $user->user_active !== 1 || (string) $user->user_type !== '1') {
            Auth::logout();
            $request->session()->invalidate();

            return LegacyRedirect::to($request, url('sessions/login'));
        }

        $response = $next($request);

        // Admin_Controller::setCacheHeaders()
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', (string) config('ledgerlift.x_frame_options'));
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
