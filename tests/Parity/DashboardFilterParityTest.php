<?php

use Illuminate\Database\Connection;
use Tests\Parity\Support\AppClient;
use Tests\Parity\Support\ParityDatabases;
use Tests\Parity\Support\TableText;

/*
 * The dashboard and the list filters, driven through the same endpoints on both apps. The data is built
 * through the apps' own screens (client form, invoice create/save, payments/ajax/add), with dates relative
 * to today because the overview and the overdue flag read the clock (NOW(), UTC on both).
 * Fixture: tax rates 1-3, products 1-6 in families Services and Hosting, clients 1-8, payment methods 1 Cash,
 * 2 Credit Card; the client "Acme Rivets" is created by the scenario (id 9).
 */

/** The same path on either app, as the apps' own links write it. */
function dparRelative(AppClient $app, string $href): string
{
    return (string) preg_replace('/_ip_csrf=[^&"\']*/', '_ip_csrf=TOKEN', str_replace([rtrim((string) env('LEGACY_URL'), '/').'/', rtrim($app->baseUrl, '/').'/'], '', $href));
}

/** A date as the m/d/Y screens take it, `$days` from today. */
function dparDate(int $days = 0): string
{
    return gmdate('m/d/Y', strtotime("$days days"));
}

/**
 * An invoice built through the create modal and the Save button, optionally paid through the payment modal.
 *
 * @return int The invoice id.
 */
function dparInvoice(AppClient $app, int $clientId, string $created, string $due, int $status, string $price, string $paid = ''): int
{
    $id = iparCreate($app, ['client_id' => (string) $clientId, 'invoice_date_created' => $created])['id'];
    $items = $price === '' ? [] : [['item_name' => 'Work', 'item_quantity' => '1', 'item_price' => $price]];
    iparSave($app, $id, [
        'invoice_number' => 'D-'.$id, 'invoice_date_created' => $created, 'invoice_date_due' => $due, 'invoice_status_id' => (string) $status, 'items' => iparItems($id, $items),
    ]);

    if ($paid !== '') {
        $app->post('payments/ajax/add', [
            'invoice_id' => (string) $id, 'payment_amount' => $paid, 'payment_method_id' => '2', 'payment_date' => $created, 'payment_note' => "Acme deposit $id",
        ], ajax: true);
    }

    return $id;
}

/**
 * Builds the company the dashboard shows: the new client "Acme Rivets" (id 9), twelve invoices in every status and
 * period (partly paid, fully paid, overdue, one a credit invoice, one recurring), and their payments.
 */
function dparBuild(AppClient $app, Connection $db): void
{
    $app->login();
    $app->get('clients/form');
    $app->post('clients/form', [
        'is_update' => '0', 'client_active' => '1', 'client_name' => 'Acme Rivets', 'client_surname' => 'Ltd', 'client_language' => 'system',
        'client_country' => 'US', 'client_email' => 'ops@acme-rivets.test', 'client_phone' => '555-0142', 'btn_submit' => '1',
    ]);

    dparInvoice($app, 1, dparDate(), dparDate(30), 1, '100.00');
    dparInvoice($app, 9, dparDate(), dparDate(30), 2, '250.40', '50.00');
    dparInvoice($app, 9, dparDate(-45), dparDate(-15), 2, '80.00');
    dparInvoice($app, 2, dparDate(-32), dparDate(-2), 3, '33.33');
    dparInvoice($app, 3, dparDate(-365), dparDate(-335), 2, '60.00', '60.00');
    dparInvoice($app, 1, dparDate(-100), dparDate(-70), 2, '10.00');
    dparInvoice($app, 2, dparDate(), dparDate(30), 2, '19.99', '19.99');
    dparInvoice($app, 4, dparDate(-400), dparDate(-370), 1, '5.00');
    $credit = dparInvoice($app, 5, dparDate(-1), dparDate(29), 2, '70.00');
    dparInvoice($app, 6, dparDate(-2), dparDate(28), 3, '12.50', '2.50');
    dparInvoice($app, 7, dparDate(-3), dparDate(27), 1, '');
    $recurring = dparInvoice($app, 8, dparDate(-4), dparDate(26), 2, '15.00');

    // A credit invoice and a recurring one are made on screens that are not ported (legacy links); set the same rows on both.
    $db->table('ip_invoice_amounts')->where('invoice_id', $credit)->update(['invoice_sign' => '-1']);
    $db->table('ip_invoices_recurring')->insert(['invoice_id' => $recurring, 'recur_start_date' => gmdate('Y-m-d'), 'recur_frequency' => '1M', 'recur_next_date' => gmdate('Y-m-d', strtotime('+1 month'))]);
}

/** Changes a setting on one side's database; the apps read it on the next request. */
function dparSet(Connection $db, string $key, string $value): void
{
    if ($db->table('ip_settings')->where('setting_key', $key)->exists()) {
        $db->table('ip_settings')->where('setting_key', $key)->update(['setting_value' => $value]);
    } else {
        $db->table('ip_settings')->insert(['setting_key' => $key, 'setting_value' => $value]);
    }
}

/** What the dashboard shows of the ported parts: quick actions, the invoice overview, the overdue panel and the recent invoices. */
function dparPage(AppClient $app): array
{
    $response = $app->get('dashboard');
    $relative = fn (string $href) => dparRelative($app, $href);
    $rows = fn (string $xpath) => TableText::rows($response->body(), $xpath);

    return [
        'status' => $response->status(),
        'quick actions' => [
            'hidden' => $app->texts('//div[@id="panel-quick-actions"]/ancestor::div[contains(@class, "row")][1]/@class'),
            'labels' => $app->texts('//div[@id="panel-quick-actions"]//a'),
            // The second button, Create Quote, differs on purpose (a link to legacy, not a modal); the others are the same.
            'links' => array_map($relative, $app->texts('//div[@id="panel-quick-actions"]//a[position() != 2]/@href')),
            'invoice' => $app->texts('//div[@id="panel-quick-actions"]//a[contains(@class, "create-invoice")]/@class'),
        ],
        'overview heading' => $app->texts('//div[@id="panel-invoice-overview"]/div[@class="panel-heading"]'),
        'overview' => $rows('//div[@id="panel-invoice-overview"]//table'),
        'overview classes' => $app->texts('//div[@id="panel-invoice-overview"]//td[@class="amount"]/span/@class'),
        'overview links' => array_map($relative, $app->texts('//div[@id="panel-invoice-overview"]//a/@href')),
        'overdue' => $app->texts('//div[@id="panel-invoice-overview"]/following-sibling::div[contains(@class, "panel-heading")]'),
        'overdue classes' => $app->texts('//div[@id="panel-invoice-overview"]/following-sibling::div[contains(@class, "panel-heading")]/@class'),
        'overdue links' => array_map($relative, $app->texts('//div[@id="panel-invoice-overview"]/following-sibling::div[contains(@class, "panel-heading")]//a/@href')),
        'recent heading' => $app->texts('(//div[@id="panel-recent-invoices"])[1]/div[@class="panel-heading"]'),
        'recent' => $rows('//div[@id="panel-recent-invoices"]//table'),
        'recent labels' => $app->texts('(//div[@id="panel-recent-invoices"])[1]//td/span[contains(@class, "label")]/@class'),
        'recent icons' => $app->texts('(//div[@id="panel-recent-invoices"])[1]//td/span[contains(@class, "label")]/i/@class'),
        'recent overdue' => $app->texts('(//div[@id="panel-recent-invoices"])[1]//td/span[contains(@class, "font-overdue")]'),
        'recent links' => array_map($relative, $app->texts('(//div[@id="panel-recent-invoices"])[1]//a/@href')),
    ];
}

/** The rows each filter endpoint answers for each query, with the status and type of the answer. */
function dparFilters(AppClient $app): array
{
    $common = ['acme', 'ACME rivets', 'acme 2026', '%', '_', 'nomatch-xyz', '', '  acme   ', "acme\tx"];
    $queries = [
        'filter_invoices' => [...$common, gmdate('Y-m'), '250.40', 'acme 100', 'd-', '$'],
        'filter_clients' => [...$common, 'rivets', 'ashgrove 555-0101', '1'],
        'filter_products' => [...$common, 'hosting', 'SRV 95', '19.9'],
        'filter_payments' => [...$common, 'deposit', 'credit acme', gmdate('Y-m-d'), '50.00'],
        'filter_families' => [...$common, 'host', 'ser vices'],
    ];

    $steps = [];

    foreach ($queries as $endpoint => $list) {
        foreach ($list as $query) {
            $response = $app->post("filter/ajax/$endpoint", ['filter_query' => $query], ajax: true);
            $steps["$endpoint: ".json_encode($query)] = [
                'status' => $response->status(),
                'type' => strtolower($response->header('Content-Type')),
                'rows' => TableText::rows($response->body()),
                'links' => array_map(fn (string $href) => dparRelative($app, $href), dparHrefs($response->body())),
            ];
        }
    }

    return $steps;
}

function dparDocument(string $html): DOMDocument
{
    $document = new DOMDocument;
    $document->loadHTML($html === '' ? '<html></html>' : $html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return $document;
}

/** @return list<string> */
function dparHrefs(string $html): array
{
    $hrefs = [];

    foreach ((new DOMXPath(dparDocument($html)))->query('//a/@href|//form/@action') ?: [] as $node) {
        $hrefs[] = $node->nodeValue ?? '';
    }

    return $hrefs;
}

it('shows the same dashboard for every overview period, and the same filter results', function (array $settings) {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        dparBuild($app, $db);

        $steps = ['dashboard' => dparPage($app), 'filters' => dparFilters($app)];

        foreach (['this-month', 'last-month', 'this-quarter', 'last-quarter', 'this-year', 'last-year', 'bogus', ''] as $period) {
            dparSet($db, 'invoice_overview_period', $period);
            $steps["period $period"] = dparPage($app);
        }

        dparSet($db, 'disable_quickactions', '1');
        $steps['quick actions off'] = dparPage($app)['quick actions'];

        return $steps;
    }, $settings);

    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        // The build is not empty: acme matches a client, invoices and payments; the overview has money in it.
        ->and(count($result->legacy['filters']['filter_invoices: "acme"']['rows']))->toBeGreaterThan(2)
        ->and(count($result->legacy['filters']['filter_clients: "acme"']['rows']))->toBe(2)
        ->and(count($result->legacy['filters']['filter_payments: "acme"']['rows']))->toBeGreaterThan(2)
        ->and(count($result->legacy['filters']['filter_invoices: "nomatch-xyz"']['rows']))->toBe(1)
        ->and(count($result->legacy['dashboard']['recent']))->toBe(12)
        ->and($result->legacy['dashboard']['overdue'])->not->toBe(['No overdue Invoice']);
})->with([
    'default settings' => [[]],
    'euro after the amount' => [['currency_symbol' => '€', 'currency_symbol_placement' => 'afterspace', 'invoice_overview_period' => 'this-year']],
]);

it('shows the empty company: no invoices, no overdue, every filter on the fixture', function () {
    $result = parity(function (AppClient $app) {
        $app->login();

        return ['dashboard' => dparPage($app), 'filters' => dparFilters($app)];
    });

    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy['dashboard']['overdue'])->toBe(['No overdue Invoice'])
        ->and(count($result->legacy['dashboard']['recent']))->toBe(2);
});
