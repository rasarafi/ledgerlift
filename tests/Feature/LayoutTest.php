<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->post('/sessions/login', [
        'email' => 'admin@ledgerlift.test', 'password' => 'ledgerlift-demo-1', 'btn_login' => 'true',
    ]);
});

it('renders the dashboard inside the ported layout', function () {
    $token = csrf_token();

    $this->continueSession()->get('/dashboard')
        ->assertOk()
        ->assertSee('<meta name="csrf_token_name" content="_ip_csrf">', false)
        ->assertSee('<meta name="csrf_token_value" content="'.$token.'">', false)
        ->assertSee('<meta name="legacy_calculation" content="1">', false)
        ->assertSee('<body class="hidden-sidebar">', false)
        ->assertSee('<div id="main-content">', false)
        ->assertSee('<div id="modal-placeholder"></div>', false)
        ->assertSee('<div id="fullpage-loader"', false)
        ->assertSee('class="hidden-md">Dashboard</a>', false)
        ->assertSee('assets/invoiceplane/css/style.css?v=1.7.2', false)
        ->assertSee('assets/core/js/dependencies.min.js?v=1.7.2', false)
        ->assertSee('assets/core/js/scripts.min.js?v=1.7.2', false)
        ->assertSee("format: 'mm/dd/yyyy'", false);
});

it('titles pages ledgerlift unless custom_title is set', function () {
    $this->continueSession()->get('/dashboard')->assertSee('<title>ledgerlift</title>', false);

    DB::table('ip_settings')->insert(['setting_key' => 'custom_title', 'setting_value' => 'Acme Billing']);
    $this->continueSession()->get('/dashboard')->assertSee('<title>Acme Billing</title>', false);
});

it('opens quotes on the legacy app with plain links, not cross-origin ajax modals', function () {
    $this->continueSession()->get('/dashboard')
        ->assertDontSee('quotes/ajax', false)
        ->assertDontSee('create-quote', false)
        ->assertSee('<a href="http://localhost:8091/quotes/index">Create Quote</a>', false)
        ->assertSee('load("'.url('invoices/ajax/modal_create_invoice').'")', false);
});

it('never links the trademarked InvoicePlane logo files', function () {
    $this->continueSession()->get('/dashboard')->assertDontSee('logo_200x100', false)->assertDontSee('logo_400x200', false);
});

it('sends migrated modules to this app and the rest to the legacy app', function () {
    $this->continueSession()->get('/dashboard')
        ->assertSee('href="'.url('clients/form').'"', false)
        ->assertSee('href="'.url('invoices/index').'"', false)
        ->assertSee('href="'.url('payments/form').'"', false)
        ->assertSee('href="'.url('products/index').'"', false)
        ->assertSee('href="'.url('families/index').'"', false)
        ->assertSee('href="'.url('units/index').'"', false)
        ->assertSee('href="'.url('invoice_groups/index').'"', false)
        ->assertSee('href="'.url('payment_methods/index').'"', false)
        ->assertSee('href="'.url('tax_rates/index').'"', false)
        ->assertSee('href="'.url('sessions/logout').'"', false)
        ->assertSee('href="http://localhost:8091/quotes/index"', false)
        ->assertSee('href="http://localhost:8091/reports/invoice_aging"', false)
        ->assertSee('href="http://localhost:8091/users/index"', false)
        ->assertSee('href="http://localhost:8091/settings"', false)
        ->assertSee('href="http://localhost:8091/payments/online_logs"', false);
});

it('shows the signed-in user in the navbar', function () {
    $id = DB::table('ip_users')->value('user_id');
    DB::table('ip_users')->where('user_id', $id)->update(['user_name' => 'A <b> & Co', 'user_company' => 'Acme']);

    $this->continueSession()->get('/dashboard')
        ->assertSee('href="http://localhost:8091/users/form/'.$id.'"', false)
        ->assertSee('title="A &lt;b&gt; &amp; Co (Acme)"', false);
});

it('hides the tasks menu unless projects are enabled and honours the sidebar setting', function () {
    DB::table('ip_settings')->where('setting_key', 'projects_enabled')->update(['setting_value' => '0']);

    $this->continueSession()->get('/dashboard')->assertSee('<li class="dropdown hidden">', false);
});

it('shows flash alerts the way legacy does', function () {
    $this->continueSession()
        ->withSession(['alert_success' => 'Saved <b>ok</b>', 'alert_error' => 'Nope', 'alert_warning' => 'Careful'])
        ->get('/dashboard')
        ->assertSee('<div class="alert alert-success alert-dismissible" role="alert">', false)
        ->assertSee('<i class="fa fa-fw fa-lg fa-info-circle"></i><span>Saved <b>ok</b></span>', false)
        ->assertSee('<div class="alert alert-warning alert-dismissible" role="alert">', false)
        ->assertSee('<i class="fa fa-fw fa-lg fa-exclamation-circle"></i><span>Careful</span>', false)
        ->assertSee('<div class="alert alert-danger alert-dismissible" role="alert">', false)
        ->assertSee('<i class="fa fa-fw fa-lg fa-warning"></i><span>Nope</span>', false);
});
