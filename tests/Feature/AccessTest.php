<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'admin'])->get('/clients', fn () => 'clients');
    Route::middleware(['web', 'ajax', 'admin'])->get('/probe/ajax', fn () => 'ajax body');
    Route::middleware('web')->post('/probe/delete/1', fn () => 'deleted');
});

function signIn(mixed $test): void
{
    $test->post('/sessions/login', [
        'email' => 'admin@ledgerlift.test', 'password' => 'ledgerlift-demo-1', 'btn_login' => 'true',
    ]);
}

it('redirects a guest to the login page', function () {
    $this->get('/clients')->assertRedirect(url('sessions/login'))->assertStatus(307);
    $this->get('/dashboard')->assertRedirect(url('sessions/login'));
});

it('serves the dashboard at the site root, as legacy default_controller does', function () {
    $this->get('/')->assertRedirect(url('sessions/login'));

    signIn($this);
    $this->continueSession()->get('/')->assertOk()->assertSee('Dashboard');
});

it('lets an active admin through', function () {
    signIn($this);

    $this->continueSession()->get('/clients')->assertOk()->assertSee('clients');
});

it('drops the session when the account is deactivated mid-session', function () {
    signIn($this);
    $this->continueSession()->get('/dashboard')->assertOk();

    User::query()->where('user_email', 'admin@ledgerlift.test')->update(['user_active' => 0]);

    $this->continueSession()->get('/dashboard')->assertRedirect(url('sessions/login'));
    expect(Auth::check())->toBeFalse();

    // The old session id no longer carries a login.
    $this->continueSession()->get('/clients')->assertRedirect(url('sessions/login'));
});

it('drops the session when the account stops being an admin', function () {
    signIn($this);
    User::query()->where('user_email', 'admin@ledgerlift.test')->update(['user_type' => 2]);

    $this->continueSession()->get('/dashboard')->assertRedirect(url('sessions/login'));
    expect(Auth::check())->toBeFalse();
});

it('drops the session when the account is deleted mid-session', function () {
    signIn($this);
    User::query()->where('user_email', 'admin@ledgerlift.test')->delete();

    $this->continueSession()->get('/dashboard')->assertRedirect(url('sessions/login'));
});

it('answers GET on a delete url with 404, registered or not', function () {
    $this->get('/clients/delete/1')->assertNotFound();
    $this->get('/probe/delete/1')->assertNotFound();
    $this->put('/probe/delete/1')->assertNotFound();
    $this->post('/probe/delete/1')->assertOk()->assertSee('deleted');
});

it('answers an ajax route without X-Requested-With with a blank 200', function () {
    signIn($this);

    $this->continueSession()->get('/probe/ajax')->assertOk()->assertContent('');
    $this->continueSession()->get('/probe/ajax', ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->assertContent('ajax body');
});

it('checks the ajax header before the login', function () {
    $this->get('/probe/ajax')->assertOk()->assertContent('');
    $this->get('/probe/ajax', ['X-Requested-With' => 'XMLHttpRequest'])->assertRedirect(url('sessions/login'));
});

it('sends the legacy admin response headers', function () {
    signIn($this);

    $this->continueSession()->get('/dashboard')
        ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
        ->assertHeader('Pragma', 'no-cache')
        ->assertHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});
