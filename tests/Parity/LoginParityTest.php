<?php

use Tests\Parity\Support\AppClient;
use Tests\Parity\Support\ParityDatabases;

const ADMIN_EMAIL = 'admin@ledgerlift.test';

it('logs in with valid credentials and lands on the dashboard', function () {
    $result = parity(function (AppClient $app) {
        $app->get('sessions/login');
        $login = $app->post('sessions/login', ['email' => ADMIN_EMAIL, 'password' => 'ledgerlift-demo-1', 'btn_login' => 'true']);
        $location = $app->location();
        $dashboard = $app->get('dashboard');

        return [
            'login_status' => $login->status(),
            'login_location' => $location,
            'dashboard_status' => $dashboard->status(),
        ];
    });

    expect($result->legacy)->toBe(['login_status' => 303, 'login_location' => 'dashboard', 'dashboard_status' => 200]);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('rejects a wrong password three times and counts the failures in ip_login_log', function () {
    $result = parity(function (AppClient $app, string $side) {
        $app->get('sessions/login');

        $redirects = [];
        foreach (range(1, 3) as $attempt) {
            $response = $app->post('sessions/login', ['email' => ADMIN_EMAIL, 'password' => "wrong-$attempt", 'btn_login' => 'true']);
            $redirects[] = [$response->status(), $app->location()];
        }

        $app->get('sessions/login');

        return [
            'redirects' => $redirects,
            'alert' => $app->text('//div[contains(@class, "alert-danger")]/span'),
            'login_log' => ParityDatabases::connection($side)
                ->table('ip_login_log')->orderBy('login_name')->pluck('log_count', 'login_name')->all(),
        ];
    });

    expect($result->legacy['redirects'])->toBe(array_fill(0, 3, [303, 'sessions/login']))
        ->and($result->legacy['alert'])->toBe('Email or Password incorrect.')
        // One row for the account and one for the client ip (login_ip:<sha256>), 3 failures each.
        ->and($result->legacy['login_log'])->toHaveCount(2)
        ->and($result->legacy['login_log'][ADMIN_EMAIL])->toBe(3)
        ->and(array_keys($result->legacy['login_log'])[1])->toMatch('/^login_ip:[0-9a-f]{64}$/')
        ->and(array_values($result->legacy['login_log']))->toBe([3, 3]);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('logs out, after which the dashboard redirects to the login page', function () {
    $result = parity(function (AppClient $app) {
        $app->login();
        $steps = [];

        foreach (['sessions/logout', 'dashboard'] as $path) {
            $steps[$path] = [$app->get($path)->status(), $app->location()];
        }

        return $steps;
    });

    expect($result->legacy)->toBe(['sessions/logout' => [307, 'sessions/login'], 'dashboard' => [307, 'sessions/login']]);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});
