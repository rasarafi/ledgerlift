<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

const ADMIN_EMAIL = 'admin@ledgerlift.test';
const ADMIN_PASSWORD = 'ledgerlift-demo-1';

function ipLogKey(string $ip = '127.0.0.1'): string
{
    return 'login_ip:'.hash('sha256', $ip);
}

function attemptLogin(mixed $test, string $password = 'wrong', string $email = ADMIN_EMAIL): mixed
{
    return $test->post('/sessions/login', ['email' => $email, 'password' => $password, 'btn_login' => 'true']);
}

it('shows the login form with a CSRF hidden input', function () {
    $response = $this->get('/sessions/login');

    $response->assertOk()
        ->assertSee('<h1>Login</h1>', false)
        ->assertSee('name="_ip_csrf" value="'.csrf_token().'"', false)
        ->assertSee('name="btn_login" value="true"', false)
        ->assertSee('href="http://localhost:8091/sessions/passwordreset"', false);
});

it('names the session cookie ledgerlift_session', function () {
    $this->get('/sessions/login')->assertCookie('ledgerlift_session');
});

it('sends the bare sessions url to the login form', function () {
    $this->get('/sessions')->assertRedirect(url('sessions/login'));
});

it('logs an admin in and redirects to the dashboard', function () {
    $response = attemptLogin($this, ADMIN_PASSWORD);

    $response->assertRedirect(url('dashboard'))->assertStatus(303);
    expect(Auth::user()?->user_email)->toBe(ADMIN_EMAIL);
});

it('regenerates the session id on login', function () {
    $this->get('/sessions/login');
    $before = session()->getId();

    attemptLogin($this, ADMIN_PASSWORD);

    expect(session()->getId())->not->toBe($before);
});

it('sends a guest-type user to the guest module on the legacy app', function () {
    User::query()->where('user_email', ADMIN_EMAIL)->update(['user_type' => 2]);

    attemptLogin($this, ADMIN_PASSWORD)->assertRedirect('http://localhost:8091/guest');
});

it('flashes the generic error and returns to the login page on a wrong password', function () {
    attemptLogin($this)
        ->assertRedirect(url('sessions/login'))
        ->assertSessionHas('alert_error', __('ip.loginalert_credentials_incorrect'));

    expect(Auth::check())->toBeFalse();
});

it('shows the flashed error on the next login page only', function () {
    attemptLogin($this);

    $this->continueSession()->get('/sessions/login')
        ->assertSee('alert-danger', false)
        ->assertSee(__('ip.loginalert_credentials_incorrect'));

    $this->continueSession()->get('/sessions/login')->assertDontSee(__('ip.loginalert_credentials_incorrect'));
});

it('refuses an unknown email, an inactive user and an empty psalt', function () {
    attemptLogin($this, ADMIN_PASSWORD, 'nobody@ledgerlift.test')->assertRedirect(url('sessions/login'));
    expect(Auth::check())->toBeFalse();

    User::query()->where('user_email', ADMIN_EMAIL)->update(['user_active' => 0]);
    attemptLogin($this, ADMIN_PASSWORD)->assertRedirect(url('sessions/login'));
    expect(Auth::check())->toBeFalse();

    // Legacy would take an MD5 path here (Mdl_sessions.php:40); ledgerlift refuses it.
    User::query()->where('user_email', ADMIN_EMAIL)->update(['user_active' => 1, 'user_psalt' => '']);
    attemptLogin($this, ADMIN_PASSWORD)->assertRedirect(url('sessions/login'));
    expect(Auth::check())->toBeFalse();
});

it('ignores a GET to the login url that carries credentials', function () {
    $this->get('/sessions/login?email='.ADMIN_EMAIL.'&password='.ADMIN_PASSWORD.'&btn_login=true')->assertOk();

    expect(Auth::check())->toBeFalse();
});

it('records failures in ip_login_log under the email and the hashed ip', function () {
    attemptLogin($this);
    attemptLogin($this);
    attemptLogin($this, 'wrong', 'other@ledgerlift.test');

    $rows = DB::table('ip_login_log')->pluck('log_count', 'login_name')->all();
    $expected = [ADMIN_EMAIL => 2, 'other@ledgerlift.test' => 1, ipLogKey() => 3];
    ksort($rows);
    ksort($expected);

    expect($rows)->toBe($expected)->and(DB::table('ip_login_log')->where('login_name', ADMIN_EMAIL)->value('log_create_timestamp'))
        ->toMatch('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/');
});

it('locks the account after 10 failures, even for the right password', function () {
    foreach (range(1, 10) as $_) {
        attemptLogin($this);
    }

    expect(DB::table('ip_login_log')->where('login_name', ADMIN_EMAIL)->value('log_count'))->toBe(10);

    attemptLogin($this, ADMIN_PASSWORD)->assertRedirect(url('sessions/login'));

    expect(Auth::check())->toBeFalse()
        ->and(DB::table('ip_login_log')->where('login_name', ADMIN_EMAIL)->value('log_count'))->toBe(10)
        ->and(DB::table('ip_login_log')->where('login_name', ipLogKey())->value('log_count'))->toBe(10);
});

it('lets the account in again once the 12 hour lock has run out', function () {
    DB::table('ip_login_log')->insert([
        'login_name' => ADMIN_EMAIL,
        'log_count' => 10,
        'log_create_timestamp' => now()->subHours(13)->format('Y-m-d H:i:s'),
    ]);

    attemptLogin($this, ADMIN_PASSWORD)->assertRedirect(url('dashboard'));

    expect(DB::table('ip_login_log')->where('login_name', ADMIN_EMAIL)->exists())->toBeFalse();
});

it('keeps the lock inside the 12 hour window', function () {
    DB::table('ip_login_log')->insert([
        'login_name' => ADMIN_EMAIL,
        'log_count' => 10,
        'log_create_timestamp' => now()->subHours(11)->format('Y-m-d H:i:s'),
    ]);

    attemptLogin($this, ADMIN_PASSWORD)->assertRedirect(url('sessions/login'));
    expect(Auth::check())->toBeFalse();
});

it('limits an ip to 20 failed attempts per 15 minutes', function () {
    DB::table('ip_login_log')->insert([
        'login_name' => ipLogKey(),
        'log_count' => 20,
        'log_create_timestamp' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
    ]);

    attemptLogin($this, ADMIN_PASSWORD)->assertRedirect(url('sessions/login'));

    expect(Auth::check())->toBeFalse()
        ->and(DB::table('ip_login_log')->count())->toBe(1);
});

it('restarts the ip counter once its window has passed', function () {
    DB::table('ip_login_log')->insert([
        'login_name' => ipLogKey(),
        'log_count' => 20,
        'log_create_timestamp' => now()->subMinutes(20)->format('Y-m-d H:i:s'),
    ]);

    attemptLogin($this);

    expect(DB::table('ip_login_log')->where('login_name', ipLogKey())->value('log_count'))->toBe(1);
});

it('clears both counters on a successful login', function () {
    attemptLogin($this);
    attemptLogin($this, ADMIN_PASSWORD)->assertRedirect(url('dashboard'));

    expect(DB::table('ip_login_log')->count())->toBe(0);
});

it('logs out and returns to the login page', function () {
    attemptLogin($this, ADMIN_PASSWORD);

    $this->continueSession()->get('/sessions/logout')->assertRedirect(url('sessions/login'))->assertStatus(307);

    expect(Auth::check())->toBeFalse();
    $this->continueSession()->get('/dashboard')->assertRedirect(url('sessions/login'));
});
