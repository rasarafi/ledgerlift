<?php

use Illuminate\Support\Facades\DB;

/*
 * The dashboard (modules/dashboard): quick actions, the invoice overview by status for the
 * configured period, the overdue panel and the ten most recent invoices. Quotes, projects and
 * tasks are not ported: one line links to the legacy app.
 */

function dashLogin(object $test): void
{
    $test->post('/sessions/login', ['email' => 'admin@ledgerlift.test', 'password' => 'ledgerlift-demo-1', 'btn_login' => 'true']);
}

/**
 * An invoice with its amounts row (total = paid + balance).
 *
 * @param  array<string, mixed>  $invoice
 */
function dashInvoice(int $status, string $created, string $due, string $paid, string $balance, array $invoice = [], string $sign = '1'): int
{
    $clientId = DB::table('ip_clients')->value('client_id') ?: DB::table('ip_clients')->insertGetId([
        'client_name' => 'Ashgrove Joinery', 'client_active' => 1, 'client_date_created' => '2026-01-05 09:00:00', 'client_date_modified' => '2026-01-05 09:00:00',
    ]);

    $id = DB::table('ip_invoices')->insertGetId($invoice + [
        'client_id' => $clientId, 'user_id' => 1, 'invoice_group_id' => 3, 'invoice_status_id' => $status, 'invoice_number' => 'D-'.random_int(1000, 9999),
        'invoice_date_created' => $created, 'invoice_date_modified' => $created.' 09:00:00', 'invoice_time_created' => '09:00:00',
        'invoice_date_due' => $due, 'invoice_url_key' => bin2hex(random_bytes(16)),
    ]);
    DB::table('ip_invoice_amounts')->insert([
        'invoice_id' => $id, 'invoice_item_subtotal' => '0.00', 'invoice_item_tax_total' => '0.00', 'invoice_tax_total' => '0.00',
        'invoice_total' => number_format((float) $paid + (float) $balance, 2, '.', ''), 'invoice_paid' => $paid, 'invoice_balance' => $balance, 'invoice_sign' => $sign,
    ]);

    return $id;
}

function dashSetting(string $key, string $value): void
{
    DB::table('ip_settings')->where('setting_key', $key)->delete();
    DB::table('ip_settings')->insert(['setting_key' => $key, 'setting_value' => $value]);
}

/** @return array<string, string> The overview panel as label => amount. */
function dashOverview(string $html): array
{
    preg_match('/id="panel-invoice-overview".*?<\/table>/s', $html, $panel);
    preg_match_all('/<a href="[^"]*">\s*(\w+)\s*<\/a>.*?<span class="\w+">\s*(.*?)\s*<\/span>/s', $panel[0] ?? '', $rows, PREG_SET_ORDER);

    return array_column($rows, 2, 1);
}

it('sends a guest to the login page', function () {
    $this->get('/dashboard')->assertRedirect(url('sessions/login'));
});

describe('dashboard', function () {
    beforeEach(function () {
        dashLogin($this);
        $this->today = gmdate('Y-m-d');
        $this->later = gmdate('Y-m-d', strtotime('+30 days'));
    });

    it('shows the status totals: paid sums what was paid, every other status the balance', function () {
        dashInvoice(1, $this->today, $this->later, '0.00', '10.00');
        dashInvoice(1, $this->today, $this->later, '0.00', '5.50');
        dashInvoice(2, $this->today, $this->later, '20.00', '80.00');
        dashInvoice(3, $this->today, $this->later, '0.00', '33.33');
        dashInvoice(4, $this->today, $this->later, '120.00', '0.00');

        $html = $this->continueSession()->get('/dashboard')->assertOk()->assertSee('Invoice Overview')->getContent();

        expect(dashOverview($html))->toBe(['Draft' => '$15.50', 'Sent' => '$80.00', 'Viewed' => '$33.33', 'Paid' => '$120.00']);
        expect($html)->toContain(url('invoices/status/draft'))->toContain(url('invoices/status/paid'));
    });

    it('counts only the invoices created in the configured period', function (string $period, string $label, string $sent) {
        // Today and the same day a year ago: whichever month it is, only the first is in this month, quarter and year.
        dashInvoice(2, $this->today, $this->later, '0.00', '1.00');
        dashInvoice(2, gmdate('Y-m-d', strtotime('-1 year')), $this->later, '0.00', '100.00');
        dashSetting('invoice_overview_period', $period);

        $html = $this->continueSession()->get('/dashboard')->assertOk()->assertSee($label)->getContent();

        expect(dashOverview($html)['Sent'])->toBe($sent);
    })->with([
        'this month' => ['this-month', 'This Month', '$1.00'],
        'last month' => ['last-month', 'Last Month', '$0.00'],
        'this quarter' => ['this-quarter', 'This Quarter', '$1.00'],
        'last quarter' => ['last-quarter', 'Last Quarter', '$0.00'],
        'this year' => ['this-year', 'This Year', '$1.00'],
        'last year' => ['last-year', 'Last Year', '$100.00'],
        'unknown falls back to this month' => ['sometime', 'Invoice Overview', '$1.00'],
    ]);

    it('takes "last month" and "last quarter" from MySQL\'s clock and compares them with its current year, as legacy does', function (string $now, string $period, string $sent) {
        // Mdl_invoice_amounts.php:379-406: MONTH/QUARTER(NOW() - INTERVAL 1 ...) but YEAR(NOW()), so in
        // January (or the first quarter) the period is December (or Q4) of the current year, not last year's.
        DB::statement('SET TIMESTAMP = '.strtotime("$now 12:00:00 UTC"));
        foreach (['2025-11-10' => '1.00', '2025-12-10' => '2.00', '2026-02-10' => '4.00', '2026-03-10' => '8.00', '2026-04-10' => '16.00', '2026-11-10' => '32.00', '2026-12-10' => '64.00'] as $created => $balance) {
            dashInvoice(2, $created, '2027-01-31', '0.00', $balance);
        }
        dashSetting('invoice_overview_period', $period);

        expect(dashOverview($this->continueSession()->get('/dashboard')->assertOk()->getContent())['Sent'])->toBe($sent);
    })->with([
        'April, last month: March' => ['2026-04-15', 'last-month', '$8.00'],
        'April, last quarter: Q1' => ['2026-04-15', 'last-quarter', '$12.00'],
        'January, last month: December of this year' => ['2026-01-15', 'last-month', '$64.00'],
        'January, last quarter: Q4 of this year' => ['2026-01-15', 'last-quarter', '$96.00'],
        'February, last quarter: Q4 of this year' => ['2026-02-15', 'last-quarter', '$96.00'],
    ]);

    it('lists the overdue invoices as one panel with their balance, and none otherwise', function () {
        $html = $this->continueSession()->get('/dashboard')->assertOk()->assertSee('No overdue Invoice')->getContent();
        expect($html)->not->toContain('panel-danger');

        $past = gmdate('Y-m-d', strtotime('-5 days'));
        dashInvoice(2, $past, $past, '0.00', '40.00');
        dashInvoice(3, $past, $past, '10.00', '2.50');
        dashInvoice(1, $past, $past, '0.00', '99.00');
        dashInvoice(4, $past, $past, '99.00', '0.00');

        $this->continueSession()->get('/dashboard')->assertOk()
            ->assertDontSee('No overdue Invoice')
            ->assertSee(url('invoices/status/overdue'), false)
            ->assertSeeInOrder(['panel-danger', 'Overdue Invoices', '$42.50']);
    });

    it('shows the ten latest invoices with their due date, signed balance and a view-all link', function () {
        foreach (range(1, 12) as $day) {
            dashInvoice(2, gmdate('Y-m-d', strtotime("-$day days")), $this->later, '0.00', '1.00', ['invoice_number' => "N-$day"]);
        }
        dashInvoice(2, $this->today, $this->later, '0.00', '7.00', ['invoice_number' => ''], '-1');

        $html = $this->continueSession()->get('/dashboard')->assertOk()->assertSee('Recent Invoices')->getContent();
        preg_match('/id="panel-recent-invoices".*?<\/table>/s', $html, $panel);

        expect(substr_count($panel[0], '<tr>'))->toBe(1 + 10 + 1)
            ->and($panel[0])->toContain('N-1<')->toContain('N-9<')->not->toContain('N-10<')
            ->and($panel[0])->toContain('$-7.00')
            ->and($panel[0])->toContain(url('invoices/status/all'));
    });

    it('marks an overdue due date', function () {
        $past = gmdate('Y-m-d', strtotime('-5 days'));
        dashInvoice(2, $past, $past, '0.00', '40.00');

        $this->continueSession()->get('/dashboard')->assertSee('font-overdue', false);
    });

    it('offers the quick actions, and hides them with disable_quickactions', function () {
        $this->continueSession()->get('/dashboard')->assertOk()
            ->assertSee('Quick Actions')
            ->assertDontSee('row hidden', false)
            ->assertSee(url('clients/form'), false)
            ->assertSee(url('payments/form'), false)
            ->assertSee('class="create-invoice btn btn-default"', false);

        dashSetting('disable_quickactions', '1');
        $this->continueSession()->get('/dashboard')->assertSee('row hidden', false);
    });

    it('replaces the quote, project and task panels with one link to the legacy app', function () {
        dashSetting('projects_enabled', '1');
        $legacy = rtrim((string) config('ledgerlift.legacy_url'), '/');

        $html = $this->continueSession()->get('/dashboard')->assertOk()->getContent();

        expect($html)->toContain($legacy.'/quotes/index')
            ->and($html)->not->toContain('panel-quote-overview')->not->toContain('panel-recent-quotes')->not->toContain('panel-projects')
            ->and($html)->not->toContain('create-quote');
    });
});
