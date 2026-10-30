<?php

use Illuminate\Database\Connection;
use Tests\Parity\Support\AppClient;
use Tests\Parity\Support\TableText;

/*
 * What the invoice and payment parity scenarios share: the endpoints the invoice screens post to
 * (create modal, Save button, tax modal) and what invoices/view and the invoice lists show a user.
 * Required once from tests/Pest.php, so each test file can run alone.
 */

/** The brief's invoice (Task 10b): VAT on A, Reduced and a per-unit discount on B, no tax on C. */
const IPAR_THREE_ITEMS = [
    ['item_name' => 'Workshop fit-out', 'item_quantity' => '1', 'item_price' => '1200.00', 'item_tax_rate_id' => '1'],
    ['item_name' => 'Hinges', 'item_quantity' => '12', 'item_price' => '19.99', 'item_tax_rate_id' => '2', 'item_discount_amount' => '1.50'],
    ['item_name' => 'Labour', 'item_quantity' => '3.5', 'item_price' => '45.00'],
];

/**
 * The create modal's post (views/invoices/modal_create_invoice.php:15-23), answered with the
 * new invoice's id.
 *
 * @param  array<string, string>  $post
 * @return array{body: string, id: int}
 */
function iparCreate(AppClient $app, array $post = []): array
{
    $response = $app->post('invoices/ajax/create', $post + [
        'client_id' => '1', 'invoice_date_created' => '10/01/2026', 'invoice_group_id' => '3', 'invoice_time_created' => '09:15:00',
        'invoice_password' => '', 'user_id' => '1', 'payment_method' => '0',
    ], ajax: true);

    return ['body' => $response->body(), 'id' => (int) $response->json('invoice_id')];
}

/**
 * The Save button's items JSON (views/invoices/view.php:73-88): each row's inputs in DOM order,
 * then item_order. A saved row carries its item_id.
 *
 * @param  list<array<string, string>>  $rows
 */
function iparItems(int $invoiceId, array $rows): string
{
    $items = [];

    foreach ($rows as $order => $row) {
        $items[] = $row + [
            'invoice_id' => (string) $invoiceId, 'item_id' => '', 'item_task_id' => '', 'item_product_id' => '', 'item_name' => '',
            'item_quantity' => '', 'item_price' => '', 'item_tax_rate_id' => '0', 'item_discount_amount' => '', 'item_description' => '',
            'item_product_unit_id' => '0', 'item_order' => $order + 1,
        ];
    }

    return (string) json_encode($items);
}

/** The rows as the page shows them after a save: each with its item_id, in item order. */
function iparSavedItems(Connection $db, int $invoiceId, array $rows): array
{
    return $db->table('ip_invoice_items')->where('invoice_id', $invoiceId)->orderBy('item_order')->pluck('item_id')
        ->map(fn (int $itemId, int $i) => ['item_id' => (string) $itemId] + $rows[$i])->all();
}

/**
 * The Save button's post (views/invoices/view.php:89-103): the raw answer.
 *
 * @param  array<string, string>  $post
 */
function iparSave(AppClient $app, int $invoiceId, array $post = []): string
{
    return $app->post('invoices/ajax/save', $post + [
        'legacy_calculation' => '1', 'invoice_id' => (string) $invoiceId, 'invoice_number' => '', 'invoice_date_created' => '10/01/2026',
        'invoice_date_due' => '10/31/2026', 'invoice_status_id' => '1', 'invoice_password' => '', 'items' => '[]',
        'invoice_discount_amount' => '', 'invoice_discount_percent' => '', 'invoice_terms' => '', 'payment_method' => '0',
    ], ajax: true)->body();
}

function iparAddTax(AppClient $app, int $invoiceId, string $taxRateId, string $includeItemTax): string
{
    return $app->post('invoices/ajax/save_invoice_tax_rate', ['invoice_id' => (string) $invoiceId, 'tax_rate_id' => $taxRateId, 'include_item_tax' => $includeItemTax], ajax: true)->body();
}

/** What invoices/view/{id} shows a user: heading, flash, the invoice's fields, its items, and the totals block. */
function iparView(AppClient $app, int $invoiceId): array
{
    $response = $app->get("invoices/view/$invoiceId");

    if ($response->status() !== 200) {
        return ['status' => $response->status(), 'location' => $app->location()];
    }

    $items = fn (string $name) => $app->texts("//tbody[@class='item']//*[@name='$name']/@value");

    return [
        'status' => 200,
        'title' => $app->texts('//h1[@class="headerbar-title"]'),
        'flash' => $app->texts('//div[contains(@class, "alert-dismissible")]/span'),
        'labels' => $app->texts('//div[contains(@class, "invoice-labels")]/span'),
        'options' => $app->texts('//div[@id="headerbar"]//ul[@class="dropdown-menu"]/li'),
        'save button' => $app->texts('//a[@id="btn_save_invoice"]'),
        'number' => [$app->text('//input[@id="invoice_number"]/@value'), $app->text('//input[@id="invoice_number"]/@placeholder')],
        'dates' => [$app->text('//input[@id="invoice_date_created"]/@value'), $app->text('//input[@id="invoice_date_due"]/@value')],
        'disabled' => $app->texts('//div[@id="invoice_form"]//*[@disabled]/@id'),
        'status' => $app->texts('//select[@id="invoice_status_id"]/option[@selected]'),
        'payment method' => $app->texts('//select[@id="payment_method"]/option[@selected]/@value'),
        'password' => $app->text('//input[@id="invoice_password"]/@value'),
        'guest url' => $app->texts('//input[@id="invoice-guest-url"]/@id'),
        'client' => $app->texts('//div[@id="invoice_form"]//h2 | //div[@class="client-address"]'),
        'items' => [
            'names' => $items('item_name'), 'quantities' => $items('item_quantity'), 'prices' => $items('item_price'),
            'discounts' => $items('item_discount_amount'), 'tax rates' => $app->texts("//tbody[@class='item']//select[@name='item_tax_rate_id']/option[@selected]/@value"),
            'amounts' => $app->texts("//tbody[@class='item']//td[contains(@class, 'td-vert-middle')]"),
        ],
        'totals' => TableText::rows($response->body(), '//table[contains(@class, "text-right")]'),
        'discounts' => [$app->text('//input[@id="invoice_discount_amount"]/@value'), $app->text('//input[@id="invoice_discount_percent"]/@value')],
        'terms' => $app->texts('//textarea[@id="invoice_terms"]'),
    ];
}

/** The rows and pager of an invoice list. */
function iparList(AppClient $app, string $path = 'invoices/status/all'): array
{
    $response = $app->get($path);
    $relative = fn (string $href) => (string) preg_replace('/_ip_csrf=[^&"\']*/', '_ip_csrf=TOKEN', str_replace([rtrim((string) env('LEGACY_URL'), '/').'/', rtrim($app->baseUrl, '/').'/'], '', $href));

    return [
        'status' => $response->status(),
        'title' => $app->texts('//h1[@class="headerbar-title"]'),
        'rows' => TableText::rows($response->body(), '//div[@id="filter_results"]//table'),
        'links' => array_map($relative, $app->texts('//div[@id="filter_results"]//a/@href | //div[@id="filter_results"]//form/@action')),
        'labels' => $app->texts('//div[@id="filter_results"]//span[contains(@class, "label")]/@class'),
        'pager' => array_map($relative, $app->texts('//div[@id="headerbar"]//div[contains(@class, "model-pager")]//a/@href')),
        'buttons' => $app->texts('//div[contains(@class, "index-options")]//a/@class'),
    ];
}

/** The stored invoice and its amounts, items and taxes. */
function iparStored(Connection $db, int $invoiceId): array
{
    return [
        'invoice' => (array) $db->table('ip_invoices')->where('invoice_id', $invoiceId)->first([
            'invoice_number', 'invoice_status_id', 'is_read_only', 'invoice_date_created', 'invoice_date_due', 'invoice_discount_amount', 'invoice_discount_percent', 'payment_method',
        ]),
        'amounts' => (array) $db->table('ip_invoice_amounts')->where('invoice_id', $invoiceId)->first([
            'invoice_item_subtotal', 'invoice_item_tax_total', 'invoice_tax_total', 'invoice_total', 'invoice_paid', 'invoice_balance',
        ]),
        'items' => $db->table('ip_invoice_items')->join('ip_invoice_item_amounts', 'ip_invoice_item_amounts.item_id', '=', 'ip_invoice_items.item_id')
            ->where('invoice_id', $invoiceId)->orderBy('item_order')
            ->get(['item_name', 'item_subtotal', 'item_tax_total', 'item_discount', 'item_total'])->map(fn (object $row) => (array) $row)->all(),
        'taxes' => $db->table('ip_invoice_tax_rates')->where('invoice_id', $invoiceId)->orderBy('invoice_tax_rate_id')->pluck('invoice_tax_rate_amount')->all(),
    ];
}
