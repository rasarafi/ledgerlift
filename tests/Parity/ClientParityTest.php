<?php

use App\Database\DatabaseSnapshot;
use App\Database\Mysql;
use Illuminate\Database\Connection;
use Tests\Parity\Support\AppClient;
use Tests\Parity\Support\DbDiff;
use Tests\Parity\Support\LegacyModel;
use Tests\Parity\Support\ParityDatabases;
use Tests\Parity\Support\TableText;

/*
 * Clients, their AJAX endpoints and the orphan cleanup, driven the same way against legacy and
 * ledgerlift. The fixture has 8 clients (1-6 active, 7-8 inactive) and no invoices; scenarios
 * that need activity insert the same rows into both databases by SQL.
 */

const CLIENT_CREATE = [
    'is_update' => '0', 'client_active' => '1', 'client_title' => 'mr', 'client_name' => 'Quillstone', 'client_surname' => 'Rowe', 'client_company' => 'Quillstone & Co',
    'client_language' => 'english', 'client_address_1' => '4 "Mill" Lane', 'client_address_2' => 'Unit <i>7</i>', 'client_city' => 'Leeds', 'client_state' => 'West Yorks',
    'client_zip' => 'LS1 4AB', 'client_country' => 'GB', 'client_invoicing_contact' => ' Jo Ledger ', 'client_phone' => '555-0199', 'client_fax' => '555-0198', 'client_mobile' => '555-0197',
    'client_email' => 'rowe@quillstone.test', 'client_web' => 'https://quillstone.test/about', 'client_vat_id' => 'GB123456', 'client_tax_code' => 'TC-9',
    'client_gender' => ' 1', 'client_birthdate' => '03/15/1980',
];

const CLIENT_EDIT = [
    'is_update' => '1', 'client_active' => '1', 'client_title' => 'custom', 'client_title_custom' => 'Sir', 'client_name' => 'Quillstone Group', 'client_surname' => '',
    'client_company' => '', 'client_language' => 'system', 'client_country' => 'US', 'client_email' => 'info@quillstone.test', 'client_web' => 'www.quillstone.test',
    'client_gender' => ' 0', 'client_birthdate' => '',
];

/** The dates MY_Model::save() writes from the clock; a scenario's own results leave them out (the database diff normalises them). */
const CLIENT_DATES = ['client_date_created' => 1, 'client_date_modified' => 1];

const CLIENT_INPUTS = [
    'is_update', 'client_name', 'client_surname', 'client_company', 'client_address_1', 'client_address_2', 'client_city', 'client_state', 'client_zip', 'client_invoicing_contact',
    'client_phone', 'client_fax', 'client_mobile', 'client_email', 'client_web', 'client_vat_id', 'client_tax_code', 'client_birthdate', 'client_title_custom',
];

/**
 * A path or URL on the app, relative to it, with the CSRF token of a link dropped. A link to a
 * module ledgerlift does not serve (the mailer) points at the legacy app; both hosts are dropped.
 */
function clientRelative(AppClient $app, string $href): string
{
    $hosts = [rtrim((string) env('LEGACY_URL'), '/').'/', rtrim($app->baseUrl, '/').'/'];

    return (string) preg_replace('/_ip_csrf=[^&"\']*/', '_ip_csrf=TOKEN', str_replace($hosts, '', $href));
}

/** What the client list shows that a user reads: heading, flash, errors, table, links, pager, status buttons. */
function clientPage(AppClient $app, string $path = 'clients/status/active'): array
{
    $response = $app->get($path);

    return [
        'status' => $response->status(),
        'location' => $app->location(),
        'title' => $app->texts('//h1[@class="headerbar-title"]'),
        'flash' => $app->texts('//div[contains(@class, "alert-dismissible")]/span'),
        'errors' => $app->texts('//div[@id="content"]//div[@class="alert alert-danger"]'),
        'search box' => $app->texts('//input[@id="filter"]/@placeholder'),
        'rows' => $response->body() === '' ? [] : TableText::rows($response->body()),
        'links' => array_map(fn (string $href) => clientRelative($app, $href), $app->texts('//table//a/@href')),
        'client ids' => $app->texts('//table//a/@data-client-id'),
        'pager' => array_map(fn (string $href) => clientRelative($app, $href), $app->texts('//div[contains(@class, "model-pager")]//a/@href')),
        'pager disabled' => $app->texts('//div[contains(@class, "model-pager")]//a[contains(@class, "disabled")]/@title'),
        'status buttons' => $app->texts('//div[contains(@class, "index-options")]//a/@class'),
    ];
}

/** The form of the last response as the user sees it. */
function clientForm(AppClient $app): array
{
    $inputs = [];
    foreach (CLIENT_INPUTS as $name) {
        $inputs[$name] = $app->text("//input[@name='$name']/@value");
    }

    $selects = [];
    foreach (['client_language', 'client_country', 'client_gender', 'client_title'] as $select) {
        $selects[$select] = [
            'options' => $app->texts("//select[@id='$select']/option"),
            'values' => $app->texts("//select[@id='$select']/option/@value"),
            'selected' => $app->texts("//select[@id='$select']/option[@selected]/@value"),
        ];
    }

    return [
        'heading' => $app->texts('//h1[@class="headerbar-title"]'),
        'panels' => $app->texts('//div[@class="panel-heading"]'),
        'labels' => $app->texts('//label'),
        'inputs' => $inputs,
        'active checked' => $app->texts('//input[@id="client_active"]/@checked'),
        'custom title class' => $app->text('//input[@id="client_title_custom"]/@class'),
        'custom title placeholder' => $app->text('//input[@id="client_title_custom"]/@placeholder'),
        'selects' => $selects,
        'errors' => $app->texts('//div[@id="content"]//div[@class="alert alert-danger"]'),
    ];
}

/** @param  array<string, string>  $data */
function clientSubmit(AppClient $app, string $path, array $data, array $buttons = ['btn_submit' => '1']): array
{
    $response = $app->post($path, $data + $buttons);

    return [
        'status' => $response->status(),
        'location' => $app->location(),
        'errors' => $app->texts('//div[@id="content"]//div[@class="alert alert-danger"]'),
        'form' => $response->redirect() ? null : clientForm($app),
    ];
}

/** The client view: its details text, tabs, and the invoice and payment tables with their links. */
function clientView(AppClient $app, string $path): array
{
    $response = $app->get($path);

    if ($response->redirect() || $response->status() !== 200) {
        return ['status' => $response->status(), 'location' => $app->location()];
    }

    $body = $response->body();
    $links = fn (string $tab) => array_map(fn (string $href) => clientRelative($app, $href), $app->texts("//div[@id='client-$tab']//a/@href"));

    return [
        'status' => $response->status(),
        'title' => $app->texts('//h1[@class="headerbar-title"]'),
        'flash' => $app->texts('//div[contains(@class, "alert-dismissible")]/span'),
        'tabs' => [
            'details' => [$app->text('//ul[@id="submenu"]/li[a/@href="#client-details"]/@class'), $app->text('//div[@id="client-details"]/@class')],
            'invoices' => [$app->text('//ul[@id="submenu"]/li[a/@href="#client-invoices"]/@class'), $app->text('//div[@id="client-invoices"]/@class')],
            'payments' => [$app->text('//ul[@id="submenu"]/li[a/@href="#client-payments"]/@class'), $app->text('//div[@id="client-payments"]/@class')],
        ],
        'buttons' => array_map(fn (string $href) => clientRelative($app, $href), $app->texts('//div[@id="headerbar"]//a/@href | //div[@id="headerbar"]//form/@action')),
        'create buttons' => $app->texts('//div[@id="headerbar"]//a/@data-client-id'),
        // Everything the details tab prints (the e-mail script included), whitespace collapsed.
        'details' => $app->texts('//div[@id="client-details"]'),
        'address' => $app->texts('//div[@id="client-details"]//p'),
        'detail links' => array_map(fn (string $href) => clientRelative($app, $href), $app->texts('//div[@id="client-details"]//a/@href')),
        'notes' => $app->texts('//div[@id="notes_list"]'),
        'note ids' => $app->texts('//span[contains(@class, "delete_client_note")]/@data-id'),
        'invoices' => TableText::rows($body, '//div[@id="client-invoices"]//table'),
        'invoice links' => $links('invoices'),
        'invoice labels' => $app->texts('//div[@id="client-invoices"]//span[contains(@class, "label")]/@class'),
        'invoice icons' => $app->texts('//div[@id="client-invoices"]//span[contains(@class, "label")]//i/@title'),
        'invoice dropups' => $app->texts('//div[@id="client-invoices"]//div[contains(@class, "options")]/@class'),
        'invoice data' => $app->texts('//div[@id="client-invoices"]//a[@data-invoice-id]/@data-invoice-balance'),
        'invoice pager' => array_map(fn (string $href) => clientRelative($app, $href), $app->texts('//div[@id="client-invoices"]//div[contains(@class, "model-pager")]//a/@href')),
        'payments' => TableText::rows($body, '//div[@id="client-payments"]//table'),
        'payment links' => $links('payments'),
        'payment pager' => array_map(fn (string $href) => clientRelative($app, $href), $app->texts('//div[@id="client-payments"]//div[contains(@class, "model-pager")]//a/@href')),
    ];
}

function clientId(string $side): int
{
    return (int) ParityDatabases::connection($side)->table('ip_clients')->max('client_id');
}

/** @return list<array<string, mixed>> */
function clientRows(string $side): array
{
    return ParityDatabases::connection($side)->table('ip_clients')->orderBy('client_id')->get()->map(fn ($row) => (array) $row)->all();
}

/**
 * The same invoices, items, payments, notes and quotes in both databases for the clients 1 and 2,
 * as the apps would write them. Client 1 has 3 invoices (one overdue, one credit, one paid, one with
 * a recurrence), 2 payments and a note; client 2 has 1 invoice, a payment, a quote and a note.
 */
function seedClientActivity(Connection $db): void
{
    $invoice = fn (int $id, int $client, int $status, string $number, string $created, string $due, array $amounts, array $extra = []) => [
        $db->table('ip_invoices')->insert([
            'invoice_id' => $id, 'client_id' => $client, 'user_id' => 1, 'invoice_group_id' => 5, 'invoice_status_id' => $status, 'invoice_number' => $number,
            'invoice_date_created' => $created, 'invoice_date_due' => $due, 'invoice_date_modified' => '2026-02-01 10:00:00', 'invoice_terms' => '',
            'invoice_url_key' => md5("key-$id"), 'payment_method' => 3,
        ] + $extra),
        $db->table('ip_invoice_amounts')->insert(['invoice_id' => $id, 'invoice_sign' => $amounts['sign'] ?? '1', 'invoice_item_subtotal' => $amounts['total'], 'invoice_total' => $amounts['total'], 'invoice_paid' => $amounts['paid'], 'invoice_balance' => $amounts['total'] - $amounts['paid']]),
        $db->table('ip_invoice_items')->insert(['item_id' => $id * 10, 'invoice_id' => $id, 'item_name' => "Item of $id", 'item_quantity' => 1, 'item_price' => $amounts['total'], 'item_date_added' => '2026-02-01']),
        $db->table('ip_invoice_item_amounts')->insert(['item_id' => $id * 10, 'item_subtotal' => $amounts['total'], 'item_total' => $amounts['total']]),
    ];

    $invoice(10, 1, 2, 'INV-0010', '2026-02-01', '2026-03-03', ['total' => 100, 'paid' => 40]);
    $invoice(11, 1, 4, 'INV-0011', '2026-02-05', '2026-03-07', ['total' => 250.5, 'paid' => 250.5]);
    $invoice(12, 1, 1, '', '2026-02-09', '2099-01-01', ['total' => 19.9, 'paid' => 0], ['is_read_only' => 1]);
    $invoice(13, 1, 3, 'INV-0013', '2026-02-12', '2020-01-01', ['total' => -30, 'paid' => 0, 'sign' => '-1']);
    $invoice(20, 2, 2, 'INV-0020 <b>', '2026-02-02', '2026-03-04', ['total' => 80, 'paid' => 10]);
    $db->table('ip_invoices_recurring')->insert(['invoice_id' => 10, 'recur_start_date' => '2026-02-01', 'recur_frequency' => '1M', 'recur_next_date' => '2026-03-01']);
    $db->table('ip_invoice_tax_rates')->insert(['invoice_id' => 10, 'tax_rate_id' => 1]);

    $db->table('ip_payments')->insert([
        ['payment_id' => 30, 'invoice_id' => 10, 'payment_method_id' => 3, 'payment_date' => '2026-02-10', 'payment_amount' => 40, 'payment_note' => 'First <i>half</i>'],
        ['payment_id' => 31, 'invoice_id' => 11, 'payment_method_id' => 0, 'payment_date' => '2026-02-11', 'payment_amount' => 250.5, 'payment_note' => ''],
        ['payment_id' => 32, 'invoice_id' => 20, 'payment_method_id' => 3, 'payment_date' => '2026-02-12', 'payment_amount' => 10, 'payment_note' => 'Other client'],
    ]);
    $db->table('ip_payment_custom')->insert(['payment_id' => 30]);
    $db->table('ip_client_notes')->insert([
        ['client_note_id' => 40, 'client_id' => 1, 'client_note_date' => '2026-02-03', 'client_note' => "Call back\non <b>Monday</b>"],
        ['client_note_id' => 41, 'client_id' => 2, 'client_note_date' => '2026-02-04', 'client_note' => 'Other'],
    ]);
    $db->table('ip_client_custom')->insert([['client_id' => 1, 'client_custom_fieldid' => 1], ['client_id' => 2, 'client_custom_fieldid' => 1]]);
    $db->table('ip_quotes')->insert(['quote_id' => 50, 'client_id' => 2, 'user_id' => 1, 'invoice_group_id' => 5, 'quote_date_created' => '2026-02-01', 'quote_date_modified' => '2026-02-01 10:00:00', 'quote_date_expires' => '2026-03-01', 'quote_url_key' => md5('q50')]);
    $db->table('ip_quote_amounts')->insert(['quote_id' => 50]);
    $db->table('ip_quote_items')->insert(['item_id' => 51, 'quote_id' => 50, 'item_tax_rate_id' => 0, 'item_date_added' => '2026-02-01']);
    $db->table('ip_quote_item_amounts')->insert(['item_id' => 51]);
    $db->table('ip_quote_tax_rates')->insert(['quote_id' => 50, 'tax_rate_id' => 1]);
    $db->table('ip_user_clients')->insert([['user_id' => 1, 'client_id' => 1], ['user_id' => 1, 'client_id' => 2]]);
}

it('creates, edits and deactivates a client, showing the same forms, lists and views', function () {
    $result = parity(function (AppClient $app, string $side) {
        $app->login();
        $steps = ['start' => clientPage($app)];

        $app->get('clients/form');
        $steps['new form'] = clientForm($app);

        $steps['create'] = clientSubmit($app, 'clients/form', CLIENT_CREATE);
        $id = clientId($side);
        $steps['view after create'] = clientView($app, "clients/view/$id");
        $steps['stored after create'] = array_diff_key(clientRows($side)[$id - 1] ?? [], CLIENT_DATES);
        $steps['list after create'] = clientPage($app);

        $app->get("clients/form/$id");
        $steps['edit form'] = clientForm($app);
        $steps['edit'] = clientSubmit($app, "clients/form/$id", CLIENT_EDIT);
        $steps['view after edit'] = clientView($app, "clients/view/$id");
        $app->get("clients/form/$id");
        $steps['edit form after edit'] = clientForm($app);

        // Deactivate: the unchecked box posts nothing.
        $steps['deactivate'] = clientSubmit($app, "clients/form/$id", ['is_update' => '1', 'client_name' => 'Quillstone Group']);
        $steps['active list'] = clientPage($app, 'clients/status/active');
        $steps['inactive list'] = clientPage($app, 'clients/status/inactive');
        $steps['all list'] = clientPage($app, 'clients/status/all');
        $steps['other status'] = clientPage($app, 'clients/status/whatever');
        $steps['bare status'] = clientPage($app, 'clients/status');
        $steps['clients'] = clientPage($app, 'clients');
        $steps['stored after deactivate'] = array_diff_key(clientRows($side)[$id - 1] ?? [], CLIENT_DATES);

        return $steps;
    });

    expect($result->legacy['create'])->toMatchArray(['status' => 303])
        ->and($result->legacy['view after create']['flash'])->toBe(['Record successfully created'])
        ->and($result->legacy['view after edit']['flash'])->toBe(['Record successfully updated'])
        ->and($result->legacy['stored after create'])->toMatchArray(['client_title' => 'mr', 'client_invoicing_contact' => 'Jo Ledger', 'client_birthdate' => '1980-03-15', 'client_gender' => 1])
        ->and($result->legacy['stored after deactivate']['client_active'])->toBe(0)
        ->and($result->legacy['clients']['location'])->toBe('clients/status/active');
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('rejects invalid and duplicate saves, cancels and answers 404 the same way', function () {
    $result = parity(function (AppClient $app, string $side) {
        $app->login();
        $steps = [];

        $steps['name missing'] = clientSubmit($app, 'clients/form', ['is_update' => '0', 'client_name' => '', 'client_city' => 'Paris', 'client_gender' => ' 2']);
        $steps['bad email'] = clientSubmit($app, 'clients/form', ['is_update' => '0', 'client_name' => 'Mail', 'client_email' => 'not an email', 'client_active' => '1']);
        $steps['whitespace email'] = clientSubmit($app, 'clients/form', ['is_update' => '0', 'client_name' => 'Spaces', 'client_email' => '   ']);
        $steps['trimmed email'] = clientSubmit($app, 'clients/form', ['is_update' => '0', 'client_name' => 'Trimmed', 'client_email' => ' ok@trim.test ']);
        $steps['whitespace name'] = clientSubmit($app, 'clients/form', ['is_update' => '0', 'client_name' => ' ']);
        $steps['invalid on edit'] = clientSubmit($app, 'clients/form/1', ['is_update' => '1', 'client_name' => '', 'client_email' => 'x']);

        // The same name and surname, any case, is refused; a new surname is not; an update is never checked.
        // The fixture's clients have a NULL surname, which an empty one does not match (but a missing one does).
        $steps['first of a name'] = clientSubmit($app, 'clients/form', ['is_update' => '0', 'client_name' => 'Dupe', 'client_surname' => '']);
        $steps['duplicate'] = clientSubmit($app, 'clients/form', ['is_update' => '0', 'client_name' => 'DUPE', 'client_surname' => '']);
        $steps['duplicate of a NULL surname'] = clientSubmit($app, 'clients/form', ['is_update' => '0', 'client_name' => 'ASHGROVE joinery']);
        $steps['empty surname is not NULL'] = clientSubmit($app, 'clients/form', ['is_update' => '0', 'client_name' => 'Cinderpeak Robotics', 'client_surname' => '']);
        $steps['duplicate flash'] = clientPage($app, 'clients/form');
        $steps['same name, new surname'] = clientSubmit($app, 'clients/form', ['is_update' => '0', 'client_name' => 'Ashgrove Joinery', 'client_surname' => 'Jr']);
        $steps['update to a taken name'] = clientSubmit($app, 'clients/form/2', ['is_update' => '1', 'client_name' => 'Ashgrove Joinery']);
        $steps['no is_update'] = clientSubmit($app, 'clients/form', ['client_name' => 'Ashgrove Joinery']);
        $steps['is_update text'] = clientSubmit($app, 'clients/form', ['is_update' => 'yes', 'client_name' => 'Ashgrove Joinery']);

        $steps['token-only post'] = clientSubmit($app, 'clients/form/1', [], []);
        $steps['cancel'] = clientSubmit($app, 'clients/form/1', CLIENT_EDIT, ['btn_cancel' => '1']);
        $steps['missing client'] = clientPage($app, 'clients/form/9999');
        $steps['update of a missing id'] = clientSubmit($app, 'clients/form/9999', ['is_update' => '1', 'client_name' => 'Ghost']);
        $steps['view of a missing id'] = clientView($app, 'clients/view/9999');
        $steps['edit post without the save button'] = clientSubmit($app, 'clients/form/1', ['client_name' => ''], []);
        $steps['delete over GET'] = clientPage($app, 'clients/delete/1');
        $steps['stored'] = array_map(fn (array $row) => array_diff_key($row, CLIENT_DATES), clientRows($side));

        return $steps;
    });

    expect($result->legacy['name missing']['errors'])->toBe(['The Client Name field is required.'])
        ->and($result->legacy['bad email']['errors'])->toBe(['The Email Address field must contain a valid email address.'])
        ->and($result->legacy['duplicate'])->toMatchArray(['status' => 303, 'location' => 'clients/form'])
        ->and($result->legacy['duplicate of a NULL surname'])->toMatchArray(['status' => 303, 'location' => 'clients/form'])
        ->and($result->legacy['empty surname is not NULL']['location'])->toStartWith('clients/view/')
        ->and($result->legacy['duplicate flash']['errors'])->toBe([])
        ->and($result->legacy['view of a missing id']['status'])->toBe(404);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('stores the transformed values: trimmed fields, the birthdate, the AVS number and the custom title', function () {
    $result = parity(function (AppClient $app, string $side) {
        $app->login();
        $steps = [];
        $n = 0;

        foreach ([
            'trim' => ['client_language' => ' english ', 'client_country' => ' GB ', 'client_invoicing_contact' => "\tJo\n", 'client_email' => ' a@b.test '],
            'birthdate' => ['client_birthdate' => '12/31/1999'],
            'birthdate invalid' => ['client_birthdate' => '31/12/1999'],
            'birthdate text' => ['client_birthdate' => 'soon'],
            'avs dotted' => ['client_avs' => '756.1234.5678.97'],
            'avs digits' => ['client_avs' => '7561234567897'],
            'avs junk' => ['client_avs' => '756-1234'],
            'gender other' => ['client_gender' => ' 2'],
            'custom title' => ['client_title' => 'custom', 'client_title_custom' => 'Lord <b>Admiral</b>'],
            'custom title empty' => ['client_title' => 'custom'],
            'known title keeps the box' => ['client_title' => 'doctor', 'client_title_custom' => 'ignored'],
            'unknown title' => ['client_title' => 'Prof.'],
            'ignored fields' => ['client_date_created' => '1999-01-01', 'client_id' => '77', 'junk' => 'x'],
            // No XML configuration is installed, so a version is invalid; an empty one is stored as it is.
            'einvoicing version' => ['client_einvoicing_version' => 'ubl21', 'client_einvoicing_active' => '1'],
            'einvoicing empty' => ['client_einvoicing_version' => '', 'client_einvoicing_active' => '1'],
            'einvoicing zero' => ['client_einvoicing_version' => '0'],
            'insured' => ['client_insurednumber' => '123 <x>', 'client_veka' => 'V-1'],
        ] as $name => $extra) {
            $n++;
            $steps[$name] = clientSubmit($app, 'clients/form', ['is_update' => '0', 'client_name' => "Case $n", 'client_active' => '1'] + $extra);
        }

        $steps['stored'] = array_map(fn (array $row) => array_diff_key($row, CLIENT_DATES), clientRows($side));
        $steps['list'] = clientPage($app, 'clients/status/all');

        return $steps;
    });

    $stored = array_column($result->legacy['stored'], null, 'client_name');
    expect($stored['Case 1'])->toMatchArray(['client_language' => 'english', 'client_country' => 'GB', 'client_invoicing_contact' => 'Jo', 'client_email' => 'a@b.test'])
        ->and($stored['Case 2']['client_birthdate'])->toBe('1999-12-31')
        ->and($stored['Case 5']['client_avs'])->toBe('7561234567897')
        ->and($stored['Case 9']['client_title'])->toBe('Lord <b>Admiral</b>');
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('shows sumex fields on the form and the view when sumex is on', function () {
    $result = parity(function (AppClient $app) {
        $app->login();
        $steps = [];
        $steps['create'] = clientSubmit($app, 'clients/form', CLIENT_CREATE + ['client_avs' => '756.1234.5678.97', 'client_insurednumber' => 'I-1', 'client_veka' => 'V-1']);
        $app->get('clients/form');
        $steps['new form'] = clientForm($app);
        $steps['avs input'] = $app->texts('//input[@name="client_avs" or @name="client_insurednumber" or @name="client_veka"]/@value');
        $app->get('clients/form/9');
        $steps['edit form'] = [$app->texts('//input[@name="client_avs" or @name="client_insurednumber" or @name="client_veka"]/@value'), $app->texts('//label')];
        $steps['view'] = clientView($app, 'clients/view/9');

        return $steps;
    }, ['sumex' => '1']);

    expect($result->legacy['view']['details'][0])->toContain('756.1234.5678.97');
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('stores a name with markup as the XSS filter leaves it, with no database difference', function () {
    $result = parity(function (AppClient $app, string $side) {
        $app->login();
        $steps = [];

        // Review Focus 4: a client named `Acme <b>Ltd</b>`.
        $steps['markup name'] = clientSubmit($app, 'clients/form', ['is_update' => '0', 'client_name' => 'Acme <b>Ltd</b>', 'client_active' => '1']);
        $id = clientId($side);
        $steps['markup stored'] = ParityDatabases::connection($side)->table('ip_clients')->where('client_id', $id)->value('client_name');
        $steps['markup view'] = clientView($app, "clients/view/$id");
        $steps['markup list'] = clientPage($app, 'clients/status/active');

        foreach (['<script>alert(1)</script>', 'x"><img src=x onerror=alert(1)>', "Caf\u{e9} <a href=\"javascript:evil()\">link</a>", 'a < b > c', '%3Cscript%3E', '&lt;b&gt;', "O'Neil & Sons"] as $n => $value) {
            $steps["hostile $n"] = clientSubmit($app, 'clients/form', ['is_update' => '0', 'client_name' => $value, 'client_surname' => $value, 'client_address_1' => $value, 'client_company' => $value]);
            $hostile = clientId($side);
            $steps["hostile $n view"] = clientView($app, "clients/view/$hostile");
            $app->get("clients/form/$hostile");
            $steps["hostile $n form"] = clientForm($app);
        }

        $steps['all'] = clientPage($app, 'clients/status/all');
        $steps['stored'] = array_map(fn (array $row) => array_diff_key($row, CLIENT_DATES), clientRows($side));

        return $steps;
    });

    // Both apps hold the same cleaned value, so no intentional difference is needed.
    expect($result->legacy['markup stored'])->toBe('Acme <b>Ltd</b>');
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('shows the client view with invoices and payments, and pages and remembers each tab', function (array $settings) {
    $result = parity(function (AppClient $app, string $side) {
        seedClientActivity(ParityDatabases::connection($side));
        $app->login();
        $steps = [];

        foreach ([
            'details' => 'clients/view/1', 'detail' => 'clients/view/1/detail', 'client 2' => 'clients/view/2', 'no activity' => 'clients/view/3',
            'invoices' => 'clients/view/1/invoices', 'invoices page 2' => 'clients/view/1/invoices/2', 'invoices page 4' => 'clients/view/1/invoices/4',
            'payments' => 'clients/view/1/payments', 'payments page 1' => 'clients/view/1/payments/1',
            'invoices again' => 'clients/view/1/invoices', 'payments remembers' => 'clients/view/1/payments', 'details forgets' => 'clients/view/1',
            'unknown tab' => 'clients/view/1/whatever/2', 'after unknown tab' => 'clients/view/1/invoices', 'details' => 'clients/view/1/detail/3',
        ] as $name => $path) {
            $steps[$name] = clientView($app, $path);
        }

        $steps['list with balances'] = clientPage($app, 'clients/status/all');

        return $steps;
    }, $settings);

    expect($result->legacy['invoices']['invoices'])->not->toBeEmpty()
        ->and(json_encode($result->legacy))->toContain('Read only', 'Credit Invoice', 'Bank transfer');
    if ($settings === []) {
        expect(json_encode($result->legacy))->toContain('Recurring');
    }
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
})->with([
    'default settings' => [[]],
    'two per page, european separators, date format d/m/Y' => [['default_list_limit' => '2', 'decimal_point' => ',', 'thousands_separator' => '.', 'currency_symbol' => 'EUR ', 'date_format' => 'd/m/Y']],
    'four per page, dropups' => [['default_list_limit' => '4']],
]);

it('deletes a client and its orphans: ledgerlift for real, legacy by its model-level delete', function () {
    $deleted = [];

    $result = parity(function (AppClient $app, string $side) use (&$deleted) {
        $db = ParityDatabases::connection($side);
        seedClientActivity($db);
        $app->login();
        $steps = ['view before' => clientView($app, 'clients/view/1')];

        // Legacy v1.7.2 refuses every delete (see known-differences.md): its answer is recorded apart and the
        // model-level delete, Mdl_clients::delete() and delete_orphans(), is applied to its database by SQL.
        $delete = $app->post('clients/delete/1');
        $deleted[$side] = [$delete->status(), $app->location(), clientPage($app)['flash']];

        if ($side === 'legacy') {
            LegacyModel::deleteClient($db, 1);
        }

        $steps['after'] = clientPage($app, 'clients/status/all');
        unset($steps['after']['flash']);
        $steps['view of the deleted'] = clientView($app, 'clients/view/1');
        $steps['view of the other'] = clientView($app, 'clients/view/2');

        // A client with no activity, and one that does not exist.
        $app->post('clients/delete/3');
        if ($side === 'legacy') {
            LegacyModel::deleteClient($db, 3);
        }
        $missing = $app->post('clients/delete/9999');
        $deleted[$side.' missing'] = [$missing->status(), $app->location()];
        if ($side === 'legacy') {
            LegacyModel::deleteClient($db, 9999);
        }

        $steps['counts'] = array_map(fn (string $table) => $db->table($table)->count(), ['ip_clients', 'ip_invoices', 'ip_invoice_amounts', 'ip_invoice_items', 'ip_invoice_item_amounts', 'ip_payments', 'ip_payment_custom', 'ip_client_notes', 'ip_client_custom', 'ip_quotes', 'ip_quote_items', 'ip_user_clients']);

        return $steps;
    });

    expect($deleted['legacy'][0])->toBe(303)
        ->and($deleted['legacy'][2])->toBe(['invalid_request'])
        ->and($deleted['new'])->toBe([303, 'clients', ['Record successfully deleted']])
        // ip_user_clients is not part of delete_orphans(): the deleted client's assignment stays.
        ->and($result->legacy['counts'])->toBe([6, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 2]);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('answers name_query with the same JSON', function (array $settings) {
    $result = parity(function (AppClient $app) {
        $app->login();
        $app->post('clients/form', ['is_update' => '0', 'client_name' => 'Acme <b>Ltd</b>', 'client_surname' => 'Ac_me', 'client_title' => 'doctor', 'client_active' => '1', 'btn_submit' => '1']);
        $app->post('clients/form', ['is_update' => '0', 'client_name' => 'Zacme', 'client_surname' => '50% off', 'client_active' => '1', 'btn_submit' => '1']);
        $app->post('clients/form', ['is_update' => '0', 'client_name' => 'Inactive Acme', 'btn_submit' => '1']);
        $steps = [];

        foreach ([
            'ac' => ['query' => 'ac'], 'AC upper' => ['query' => 'AC'], 'a' => ['query' => 'a'], 'ash' => ['query' => 'ash'], 'surname' => ['query' => 'ac_'], 'full name' => ['query' => 'Acme Ac'],
            'percent' => ['query' => '%'], 'underscore' => ['query' => '_'], 'percent word' => ['query' => '5%'], 'quote' => ['query' => "a'b"], 'backslash' => ['query' => 'a\\'],
            'markup' => ['query' => '<b>'], 'zero' => ['query' => '0'], 'space' => ['query' => ' '], 'empty' => ['query' => ''], 'none' => [], 'no match' => ['query' => 'zzz'],
            'permissive' => ['query' => 'acm', 'permissive_search_clients' => '1'], 'permissive off' => ['query' => 'acm', 'permissive_search_clients' => '0'],
            'permissive text' => ['query' => 'ash', 'permissive_search_clients' => 'abc'], 'permissive wild' => ['query' => '%', 'permissive_search_clients' => '1'],
        ] as $name => $query) {
            $response = $app->get('clients/ajax/name_query', $query, ajax: true);
            $steps[$name] = ['status' => $response->status(), 'content type' => $response->header('Content-Type'), 'body' => $response->body(), 'decoded' => json_decode($response->body(), true)];
        }

        $plain = $app->get('clients/ajax/name_query', ['query' => 'ac']);
        $steps['not ajax'] = [$plain->status(), $plain->body()];

        return $steps;
    }, $settings);

    expect($result->legacy['ac']['decoded'])->toHaveCount(1)
        ->and($result->legacy['ac']['content type'])->toStartWith('text/html')
        ->and($result->legacy['not ajax'])->toBe([200, '']);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
})->with([
    'default settings' => [[]],
    'sorted by surname' => [['sort_clients_by_surname' => '1']],
]);

it('saves the permissive search preference, as setting rows', function () {
    $result = parity(function (AppClient $app, string $side) {
        $app->login();
        $steps = [];

        foreach (['1', '0', '1', '2', '', '10', 'a', "1\n", '-1'] as $value) {
            $response = $app->get('clients/ajax/save_preference_permissive_search_clients', ['permissive_search_clients' => $value], ajax: true);
            $steps["value '".addcslashes($value, "\n")."'"] = [
                'status' => $response->status(), 'body' => $response->body(),
                'stored' => ParityDatabases::connection($side)->table('ip_settings')->where('setting_key', 'enable_permissive_search_clients')->pluck('setting_value')->all(),
            ];
        }

        $steps['no value'] = $app->get('clients/ajax/save_preference_permissive_search_clients', [], ajax: true)->body();
        $steps['not ajax'] = $app->get('clients/ajax/save_preference_permissive_search_clients', ['permissive_search_clients' => '0'])->body();
        $steps['array'] = $app->get('clients/ajax/save_preference_permissive_search_clients', ['permissive_search_clients' => ['1']], ajax: true)->status();

        return $steps;
    });

    expect($result->legacy["value '1'"]['stored'])->toBe(['1']);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('filters the client list over AJAX', function () {
    $result = parity(function (AppClient $app) {
        $app->login();
        $app->post('clients/form', ['is_update' => '0', 'client_name' => '100% Wool', 'client_surname' => 'Web_Co', 'client_title' => 'mrs', 'client_email' => 'wool@x.test', 'client_phone' => '555-7', 'btn_submit' => '1']);
        $steps = [];

        foreach (['ash', 'ASH', 'ashgrove joinery', 'joinery ash', 'east', '555', '555-01', 'mrs', 'x.test', 'glass', '0', '1', '%', '_', 'web_', '100%', ' ', '', "'", '^', 'ash  grove', "\tash", 'ash ', '0%'] as $query) {
            $response = $app->post('filter/ajax/filter_clients', ['filter_query' => $query], ajax: true);
            $steps[$query] = [$response->status(), TableText::rows('<html><body>'.$response->body().'</body></html>')];
        }

        $plain = $app->post('filter/ajax/filter_clients', ['filter_query' => 'a']);
        $steps['not ajax'] = [$plain->status(), $plain->body()];

        return $steps;
    }, ['currency_symbol' => 'EUR ']);

    expect($result->legacy['ashgrove joinery'][1])->toHaveCount(1 + 1)
        ->and($result->legacy['glass'][1])->toHaveCount(1 + 1)
        ->and($result->legacy['%'][1])->toHaveCount(1 + 1)
        ->and($result->legacy['not ajax'])->toBe([200, '']);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('saves, lists and deletes client notes, with the same JSON, HTML and database', function () {
    $result = parity(function (AppClient $app, string $side) {
        $app->login();
        $steps = [];
        $json = function (string $path, array $data) use ($app) {
            $response = $app->post($path, $data, ajax: true);
            $decoded = json_decode($response->body(), true);
            unset($decoded['new_token']);

            return ['status' => $response->status(), 'content type' => $response->header('Content-Type'), 'decoded' => $decoded, 'has token' => is_string(json_decode($response->body(), true)['new_token'] ?? null)];
        };

        $steps['save'] = $json('clients/ajax/save_client_note', ['client_id' => '1', 'client_note' => "First\nsecond <b>line</b> & more"]);
        $steps['flash after save'] = clientView($app, 'clients/view/1')['flash'];
        $steps['save again'] = $json('clients/ajax/save_client_note', ['client_id' => '1', 'client_note' => 'Second note']);
        $steps['no note'] = $json('clients/ajax/save_client_note', ['client_id' => '1', 'client_note' => '']);
        $steps['no client'] = $json('clients/ajax/save_client_note', ['client_note' => 'orphan?']);
        $steps['nothing'] = $json('clients/ajax/save_client_note', ['junk' => 'x']);
        $steps['whitespace'] = $json('clients/ajax/save_client_note', ['client_id' => '1', 'client_note' => '  ']);

        $load = $app->post('clients/ajax/load_client_notes', ['client_id' => '1'], ajax: true);
        $steps['load'] = ['status' => $load->status(), 'notes' => $app->texts('//div[contains(@class, "panel-body")]'), 'footers' => $app->texts('//div[contains(@class, "panel-footer")]'), 'ids' => $app->texts('//span/@data-id')];
        $steps['load empty'] = $app->post('clients/ajax/load_client_notes', ['client_id' => '2'], ajax: true)->body() === '' ? 'blank' : $app->texts('//div');
        $steps['load none'] = $app->post('clients/ajax/load_client_notes', [], ajax: true)->body() === '' ? 'blank' : $app->texts('//div');

        $noteId = (int) ParityDatabases::connection($side)->table('ip_client_notes')->min('client_note_id');
        $steps['delete'] = $json('clients/ajax/delete_client_note', ['client_note_id' => (string) $noteId]);
        $steps['flash after delete'] = clientView($app, 'clients/view/1')['flash'];
        $steps['delete unknown'] = $json('clients/ajax/delete_client_note', ['client_note_id' => '9999']);
        $steps['flash after unknown'] = clientView($app, 'clients/view/1')['flash'];
        $steps['delete empty'] = $json('clients/ajax/delete_client_note', ['client_note_id' => '']);
        $steps['delete none'] = $json('clients/ajax/delete_client_note', []);
        $steps['not ajax'] = $app->post('clients/ajax/save_client_note', ['client_id' => '1', 'client_note' => 'plain'])->body();
        $steps['notes'] = clientView($app, 'clients/view/1')['notes'];

        return $steps;
    });

    expect($result->legacy['save']['decoded'])->toBe(['success' => 1])
        ->and($result->legacy['no note']['decoded'])->toBe(['success' => 0, 'validation_errors' => ['client_note' => '<p>The Note field is required.</p>']])
        ->and($result->legacy['save']['content type'])->toStartWith('text/html');
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('assigns a new active client to the users who see all clients', function () {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        $db->table('ip_users')->where('user_id', 1)->update(['user_all_clients' => 1]);
        $db->table('ip_user_clients')->insert(['user_id' => 1, 'client_id' => 2]);
        $app->login();
        $steps = [];

        $steps['create active'] = clientSubmit($app, 'clients/form', ['is_update' => '0', 'client_name' => 'Active New', 'client_active' => '1']);
        $steps['assignments'] = $db->table('ip_user_clients')->orderBy('user_client_id')->get()->map(fn ($row) => (array) $row)->all();
        $steps['create inactive'] = clientSubmit($app, 'clients/form', ['is_update' => '0', 'client_name' => 'Inactive New']);
        $steps['edit'] = clientSubmit($app, 'clients/form/1', ['is_update' => '1', 'client_name' => 'Ashgrove Joinery', 'client_active' => '1']);
        $steps['assignments after'] = $db->table('ip_user_clients')->orderBy('user_client_id')->get()->map(fn ($row) => (array) $row)->all();

        return $steps;
    }, ['sort_clients_by_surname' => '0']);

    // Client 2 was assigned already; the fixture's other active clients and the new one follow, in name order.
    expect(count($result->legacy['assignments']))->toBe(1 + 6);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('sends a guest to the login page from every client screen', function () {
    $result = parity(function (AppClient $app) {
        $steps = [];

        foreach (['clients', 'clients/status/active', 'clients/status/active/15', 'clients/form', 'clients/form/1', 'clients/view/1', 'clients/view/1/invoices'] as $path) {
            $steps["GET $path"] = [$app->get($path)->status(), $app->location()];
        }

        $steps['POST form'] = [$app->post('clients/form', ['client_name' => 'x', 'btn_submit' => '1'])->status(), $app->location()];
        $steps['POST delete'] = [$app->post('clients/delete/1')->status(), $app->location()];

        foreach (['clients/ajax/name_query', 'clients/ajax/save_preference_permissive_search_clients'] as $path) {
            $response = $app->get($path, ['query' => 'a'], ajax: true);
            $steps["AJAX GET $path"] = [$response->status(), $app->location(), $response->redirect() ? null : $response->body()];
            $response = $app->get($path, ['query' => 'a']);
            $steps["GET $path"] = [$response->status(), $app->location(), $response->redirect() ? null : $response->body()];
        }

        foreach (['clients/ajax/save_client_note', 'clients/ajax/delete_client_note', 'clients/ajax/load_client_notes', 'filter/ajax/filter_clients'] as $path) {
            $steps["AJAX POST $path"] = [$app->post($path, ['client_id' => '1'], ajax: true)->status(), $app->location()];
        }

        return $steps;
    });

    expect($result->legacy['GET clients'])->toBe([307, 'sessions/login']);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('pages the client list by offset, with the limit of default_list_limit', function () {
    $result = parity(function (AppClient $app) {
        $app->login();
        $pages = ['0', '1', '2', '3', '5', '6', '15'];

        return array_combine($pages, array_map(fn (string $offset) => clientPage($app, "clients/status/all/$offset"), $pages))
            + ['active' => clientPage($app, 'clients/status/active/2')]
            + ['bare' => clientPage($app, 'clients/status/all')];
    }, ['default_list_limit' => '3']);

    expect($result->legacy['0']['rows'])->toHaveCount(1 + 3)
        ->and($result->legacy['0']['pager'])->not->toBeEmpty();
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('self-check: two identical legacy runs differ only in the client dates written by date()', function () {
    $copy = 'ledgerlift_parity_rerun_test';
    $create = function (): void {
        $app = new AppClient((string) env('LEGACY_URL'));
        $app->login();
        $app->post('clients/form', ['is_update' => '0', 'client_name' => 'Rerun', 'client_active' => '1', 'btn_submit' => '1']);
        $app->post('clients/form/1', ['is_update' => '1', 'client_name' => 'Ashgrove Joinery', 'client_active' => '1', 'btn_submit' => '1']);
    };
    $legacyDb = (string) env('LEGACY_DB');

    ParityDatabases::reset();
    $create();
    DatabaseSnapshot::copy($legacyDb, $copy);

    // date('Y-m-d H:i:s') has one-second resolution; make sure the second run lands in a later second.
    sleep(1);
    ParityDatabases::reset();
    $create();

    // MY_Model::save() writes date('Y-m-d H:i:s') to both client dates. Every raw difference is one of
    // them; the timestamps themselves might coincide, so their count is bounded, not required.
    $raw = DbDiff::compare($copy, $legacyDb, normalise: []);
    $normalised = array_keys(require __DIR__.'/normalise.php');
    foreach ($raw as $difference) {
        expect($normalised)->toContain($difference['table'].'.'.$difference['column']);
        expect($difference['table'])->toBe('ip_clients');
    }
    expect(count($raw))->toBeLessThanOrEqual(4);
    expect(DbDiff::compare($copy, $legacyDb))->toBe([]);

    Mysql::connection()->statement("DROP DATABASE `$copy`");
});
