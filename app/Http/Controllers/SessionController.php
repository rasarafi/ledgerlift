<?php

namespace App\Http\Controllers;

use App\Http\Responses\LegacyRedirect;
use App\Models\User;
use App\Services\LoginThrottle;
use App\Support\Strangler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** modules/sessions/controllers/Sessions.php */
class SessionController extends Controller
{
    public function __construct(private readonly LoginThrottle $throttle) {}

    public function index(Request $request): RedirectResponse
    {
        return LegacyRedirect::to($request, url('sessions/login'));
    }

    public function login(Request $request): View|RedirectResponse
    {
        if ($request->post('btn_login')) {
            if ($this->authenticate((string) $request->post('email'), (string) $request->post('password'), $request)) {
                return LegacyRedirect::to($request, (int) Auth::user()?->user_type === 2
                    ? Strangler::url('guest')
                    : url('dashboard'));
            }

            // One generic message for every failure, so nothing reveals which accounts exist.
            return LegacyRedirect::to($request, url('sessions/login'))
                ->with('alert_error', __('ip.loginalert_credentials_incorrect'));
        }

        return view('sessions.login');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();

        return LegacyRedirect::to($request, url('sessions/login'));
    }

    /** Sessions::authenticate() (Sessions.php:337). */
    private function authenticate(string $email, string $password, Request $request): bool
    {
        if (! $request->isMethod('POST')) {
            return false;
        }

        $ip = (string) $request->ip();

        if ($this->throttle->ipLimited($ip) || $this->throttle->accountLocked($email)) {
            return false;
        }

        $user = $this->verify($email, $password);

        if ($user === null) {
            $this->throttle->recordFailure($email, $ip);

            return false;
        }

        $this->throttle->recordSuccess($email, $ip);
        Auth::login($user); // also regenerates the session id, as Mdl_sessions::auth() does

        return true;
    }

    /** Mdl_sessions::auth() (Mdl_sessions.php:22), without its MD5 path. */
    private function verify(string $email, string $password): ?User
    {
        $user = User::query()->where('user_email', $email)->first();

        // Legacy upgrades a user without user_psalt from an MD5 hash here (Mdl_sessions.php:40);
        // ledgerlift refuses them instead.
        if ($user === null || ! $user->user_psalt) {
            return null;
        }

        if (! password_verify($password, (string) $user->user_password) || (int) $user->user_active !== 1) {
            return null;
        }

        return $user;
    }
}
