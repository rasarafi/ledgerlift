<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Laravel's test client keeps no cookie jar and its auth guard outlives a request.
 * These helpers make consecutive requests behave like separate browser requests.
 */
trait InteractsWithLegacySession
{
    /**
     * Sends the next request with the session cookie of the last one a cold guard and fresh per-request services (Settings),
     * as a browser (and a fresh PHP process) would.
     */
    protected function continueSession(): static
    {
        $id = session()->getId();

        Auth::forgetGuards();
        $this->app->forgetScopedInstances();

        return $this->withCookie((string) config('session.cookie'), $id);
    }

    /**
     * Turns CSRF verification on. Laravel skips it when the environment is `testing`.
     */
    protected function enforceCsrf(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
    }
}
