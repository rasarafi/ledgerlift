<?php

const LOGIN_FIELDS = ['email' => 'admin@ledgerlift.test', 'password' => 'ledgerlift-demo-1', 'btn_login' => 'true'];

beforeEach(function () {
    $this->enforceCsrf();
});

it('refuses a POST without a token with 403, as legacy does', function () {
    $this->post('/sessions/login', LOGIN_FIELDS)->assertForbidden();
});

it('refuses a POST with the wrong token', function () {
    $this->get('/sessions/login');

    $this->continueSession()->post('/sessions/login', LOGIN_FIELDS + ['_ip_csrf' => 'nope'])->assertForbidden();
});

it('accepts the token in the _ip_csrf field', function () {
    $this->get('/sessions/login');
    $token = csrf_token();

    $this->continueSession()->post('/sessions/login', LOGIN_FIELDS + ['_ip_csrf' => $token])
        ->assertRedirect(url('dashboard'));
});

it('accepts the token in the X-CSRF-TOKEN header', function () {
    $this->get('/sessions/login');
    $token = csrf_token();

    $this->continueSession()->post('/sessions/login', LOGIN_FIELDS, ['X-CSRF-TOKEN' => $token])
        ->assertRedirect(url('dashboard'));
});

it('puts the current token in the X-_ip_csrf response header', function () {
    $response = $this->get('/sessions/login');

    $response->assertHeader('X-_ip_csrf', csrf_token());
});
