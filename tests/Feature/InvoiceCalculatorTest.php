<?php

use App\Services\InvoiceCalculator;
use Illuminate\Support\Facades\DB;

/*
 * InvoiceCalculator ports the LEGACY_CALCULATION=true maths of Mdl_item_amounts::calculate()
 * and Mdl_invoice_amounts::calculate(). MySQL rounds every amount on write (DECIMAL(20,2), the
 * invoice tax amounts DECIMAL(10,2)), so these run against the database and read back the
 * stored strings. Tax rates: 1 VAT 15.00, 2 Reduced 5.00, 3 Service 7.50.
 */

function calcTaxRates(): void
{
    DB::table('ip_tax_rates')->insert([
        ['tax_rate_id' => 1, 'tax_rate_name' => 'VAT', 'tax_rate_percent' => '15.00'],
        ['tax_rate_id' => 2, 'tax_rate_name' => 'Reduced', 'tax_rate_percent' => '5.00'],
        ['tax_rate_id' => 3, 'tax_rate_name' => 'Service', 'tax_rate_percent' => '7.50'],
    ]);
}

/**
 * A client, then an invoice of group 3 with its empty amounts row (as Mdl_invoices::create() leaves it).
 *
 * @param  array<string, mixed>  $attributes
 */
function calcInvoice(array $attributes = [], bool $amountsRow = true): int
{
    $clientId = DB::table('ip_clients')->insertGetId([
        'client_name' => 'Ashgrove Joinery', 'client_active' => 1, 'client_date_created' => '2026-01-05 09:00:00', 'client_date_modified' => '2026-01-05 09:00:00',
    ]);
    $id = DB::table('ip_invoices')->insertGetId($attributes + [
        'client_id' => $clientId, 'user_id' => 1, 'invoice_group_id' => 3, 'invoice_status_id' => 2, 'invoice_number' => '1',
        'invoice_date_created' => '2026-10-01', 'invoice_date_due' => '2026-10-31', 'invoice_date_modified' => '2026-10-01 09:00:00',
        'invoice_terms' => '', 'invoice_url_key' => md5((string) microtime()),
    ]);

    if ($amountsRow) {
        DB::table('ip_invoice_amounts')->insert(['invoice_id' => $id]);
    }

    return $id;
}

function calcItem(int $invoiceId, string $quantity, string $price, int $taxRateId = 0, ?string $discount = null): int
{
    return DB::table('ip_invoice_items')->insertGetId([
        'invoice_id' => $invoiceId, 'item_tax_rate_id' => $taxRateId, 'item_date_added' => '2026-10-01', 'item_name' => 'Item',
        'item_quantity' => $quantity, 'item_price' => $price, 'item_discount_amount' => $discount,
    ]);
}

function calcPayment(int $invoiceId, string $amount, int $methodId = 0): void
{
    DB::table('ip_payments')->insert([
        'invoice_id' => $invoiceId, 'payment_method_id' => $methodId, 'payment_date' => '2026-10-15', 'payment_amount' => $amount, 'payment_note' => '',
    ]);
}

/** Every item's amounts, then the invoice's, as legacy's save does (Ajax::save via Mdl_items::save). */
function calcRun(int $invoiceId): void
{
    $calculator = app(InvoiceCalculator::class);

    foreach (DB::table('ip_invoice_items')->where('invoice_id', $invoiceId)->pluck('item_id') as $itemId) {
        $calculator->recalculateItem((int) $itemId);
    }

    $calculator->recalculateInvoice($invoiceId);
}

/** @return array<string, mixed> */
function calcItemAmounts(int $itemId): array
{
    return (array) DB::table('ip_invoice_item_amounts')->where('item_id', $itemId)
        ->first(['item_subtotal', 'item_tax_total', 'item_discount', 'item_total']);
}

/** @return array<string, mixed> */
function calcInvoiceAmounts(int $invoiceId): array
{
    return (array) DB::table('ip_invoice_amounts')->where('invoice_id', $invoiceId)
        ->first(['invoice_item_subtotal', 'invoice_item_tax_total', 'invoice_tax_total', 'invoice_total', 'invoice_paid', 'invoice_balance']);
}

/** @return array<string, mixed> */
function calcInvoiceState(int $invoiceId): array
{
    return (array) DB::table('ip_invoices')->where('invoice_id', $invoiceId)->first(['invoice_status_id', 'is_read_only', 'payment_method']);
}

/** The brief's invoice: three items, an invoice tax Service 7.50 on items plus item tax, and 10% off. */
function calcThreeItemInvoice(): array
{
    calcTaxRates();
    $id = calcInvoice(['invoice_discount_percent' => '10.00']);
    $items = [
        calcItem($id, '1', '1200.00', 1),
        calcItem($id, '12', '19.99', 2, '1.50'),
        calcItem($id, '3.5', '45.00'),
    ];
    DB::table('ip_invoice_tax_rates')->insert(['invoice_id' => $id, 'tax_rate_id' => 3, 'include_item_tax' => 1]);

    return [$id, $items];
}

it('writes the legacy item amounts: tax on the gross subtotal, a per-unit discount', function () {
    [, [$a, $b, $c]] = calcThreeItemInvoice();
    $calculator = app(InvoiceCalculator::class);

    foreach ([$a, $b, $c] as $item) {
        $calculator->recalculateItem($item);
    }

    expect(calcItemAmounts($a))->toBe(['item_subtotal' => '1200.00', 'item_tax_total' => '180.00', 'item_discount' => '0.00', 'item_total' => '1380.00'])
        ->and(calcItemAmounts($b))->toBe(['item_subtotal' => '239.88', 'item_tax_total' => '11.99', 'item_discount' => '18.00', 'item_total' => '233.87'])
        ->and(calcItemAmounts($c))->toBe(['item_subtotal' => '157.50', 'item_tax_total' => '0.00', 'item_discount' => '0.00', 'item_total' => '157.50']);
});

it('updates an existing item amounts row instead of adding one', function () {
    calcTaxRates();
    $item = calcItem(calcInvoice(), '2', '10.00', 1);
    $calculator = app(InvoiceCalculator::class);

    $calculator->recalculateItem($item);
    DB::table('ip_invoice_items')->where('item_id', $item)->update(['item_quantity' => '3']);
    $calculator->recalculateItem($item);

    expect(DB::table('ip_invoice_item_amounts')->where('item_id', $item)->count())->toBe(1)
        ->and(calcItemAmounts($item))->toBe(['item_subtotal' => '30.00', 'item_tax_total' => '4.50', 'item_discount' => '0.00', 'item_total' => '34.50']);
});

it('recalculates the three-item invoice with its invoice tax and percent discount', function () {
    [$id] = calcThreeItemInvoice();

    calcRun($id);

    expect(calcInvoiceAmounts($id))->toBe([
        'invoice_item_subtotal' => '1579.38', 'invoice_item_tax_total' => '191.99', 'invoice_tax_total' => '132.85',
        'invoice_total' => '1713.80', 'invoice_paid' => '0.00', 'invoice_balance' => '1713.80',
    ])
        ->and(DB::table('ip_invoice_tax_rates')->where('invoice_id', $id)->value('invoice_tax_rate_amount'))->toBe('132.85')
        ->and(calcInvoiceState($id))->toBe(['invoice_status_id' => 2, 'is_read_only' => null, 'payment_method' => 0]);
});

it('takes an invoice tax without include_item_tax on the item subtotal only', function () {
    calcTaxRates();
    $id = calcInvoice();
    calcItem($id, '1', '200.00', 1);
    DB::table('ip_invoice_tax_rates')->insert(['invoice_id' => $id, 'tax_rate_id' => 3, 'include_item_tax' => 0]);

    calcRun($id);

    // 200 × 7.5% = 15.00; total 200 + 30 + 15.
    expect(DB::table('ip_invoice_tax_rates')->where('invoice_id', $id)->value('invoice_tax_rate_amount'))->toBe('15.00')
        ->and(calcInvoiceAmounts($id))->toMatchArray(['invoice_tax_total' => '15.00', 'invoice_total' => '245.00', 'invoice_balance' => '245.00']);
});

it('marks the invoice paid once the balance reaches zero, and read-only on the next recalculation', function () {
    [$id] = calcThreeItemInvoice();
    calcRun($id);

    calcPayment($id, '500.00', 3);
    calcRun($id);

    expect(calcInvoiceAmounts($id))->toMatchArray(['invoice_total' => '1713.80', 'invoice_paid' => '500.00', 'invoice_balance' => '1213.80'])
        ->and(calcInvoiceState($id))->toBe(['invoice_status_id' => 2, 'is_read_only' => null, 'payment_method' => 0]);

    calcPayment($id, '1213.80');
    calcRun($id);

    // The payment method is the first payment's; read-only waits for a status of 4 before the update.
    expect(calcInvoiceAmounts($id))->toMatchArray(['invoice_paid' => '1713.80', 'invoice_balance' => '0.00'])
        ->and(calcInvoiceState($id))->toBe(['invoice_status_id' => 4, 'is_read_only' => null, 'payment_method' => 3]);

    app(InvoiceCalculator::class)->recalculateInvoice($id);

    expect(calcInvoiceState($id))->toBe(['invoice_status_id' => 4, 'is_read_only' => 1, 'payment_method' => 3]);
});

it('never sets read-only when DISABLE_READ_ONLY is on', function () {
    config(['ledgerlift.disable_read_only' => true]);
    $id = calcInvoice(['invoice_status_id' => 4]);
    calcItem($id, '1', '50.00');
    calcPayment($id, '50.00');

    calcRun($id);

    expect(calcInvoiceState($id))->toBe(['invoice_status_id' => 4, 'is_read_only' => null, 'payment_method' => 0]);
});

it('sets read-only only when the status before the update equals read_only_toggle', function () {
    DB::table('ip_settings')->where('setting_key', 'read_only_toggle')->update(['setting_value' => '2']);
    $id = calcInvoice(['invoice_status_id' => 2]);
    calcItem($id, '1', '50.00');
    calcPayment($id, '50.00');

    calcRun($id);

    expect(calcInvoiceState($id))->toBe(['invoice_status_id' => 4, 'is_read_only' => 1, 'payment_method' => 0]);
});

it('marks even a draft paid when the balance is zero', function () {
    $id = calcInvoice(['invoice_status_id' => 1]);
    calcItem($id, '2', '25.00');
    calcPayment($id, '50.00', 3);

    calcRun($id);

    expect(calcInvoiceState($id))->toBe(['invoice_status_id' => 4, 'is_read_only' => null, 'payment_method' => 3]);
});

it('subtracts a discount amount, then rounds the percent of what is left', function () {
    $id = calcInvoice(['invoice_discount_amount' => '5.00', 'invoice_discount_percent' => '2.50']);
    calcItem($id, '1', '100.00');

    calcRun($id);

    // 100 − 5 = 95; round(95 / 100 × 2.5, 2) = 2.38; 95 − 2.38 = 92.62.
    expect(calcInvoiceAmounts($id))->toBe([
        'invoice_item_subtotal' => '100.00', 'invoice_item_tax_total' => '0.00', 'invoice_tax_total' => '0.00',
        'invoice_total' => '92.62', 'invoice_paid' => '0.00', 'invoice_balance' => '92.62',
    ]);
});

it('rounds each stored amount on write, half up, from the float maths', function () {
    calcTaxRates();
    $first = calcInvoice();
    $a = calcItem($first, '0.333', '10.00', 1);
    $second = calcInvoice();
    $b = calcItem($second, '3', '0.35', 3);

    calcRun($first);
    calcRun($second);

    // 0.333 × 10 = 3.33; 3.33 × 0.15 = 0.4995 → 0.50; 3.33 + 0.4995 = 3.8295 → 3.83.
    expect(calcItemAmounts($a))->toBe(['item_subtotal' => '3.33', 'item_tax_total' => '0.50', 'item_discount' => '0.00', 'item_total' => '3.83'])
        ->and(calcInvoiceAmounts($first))->toMatchArray(['invoice_item_subtotal' => '3.33', 'invoice_item_tax_total' => '0.50', 'invoice_total' => '3.83'])
        // 3 × 0.35 = 1.05; 1.05 × 0.075 = 0.07875 → 0.08; 1.05 + 0.07875 = 1.12875 → 1.13.
        ->and(calcItemAmounts($b))->toBe(['item_subtotal' => '1.05', 'item_tax_total' => '0.08', 'item_discount' => '0.00', 'item_total' => '1.13'])
        ->and(calcInvoiceAmounts($second))->toMatchArray(['invoice_item_subtotal' => '1.05', 'invoice_item_tax_total' => '0.08', 'invoice_total' => '1.13']);
});

it('adds the amounts row of an invoice without one, and keeps the NULL item tax of an invoice without items', function () {
    $id = calcInvoice(amountsRow: false);

    app(InvoiceCalculator::class)->recalculateInvoice($id);

    expect(DB::table('ip_invoice_amounts')->where('invoice_id', $id)->count())->toBe(1)
        ->and(calcInvoiceAmounts($id))->toBe([
            'invoice_item_subtotal' => '0.00', 'invoice_item_tax_total' => null, 'invoice_tax_total' => '0.00',
            'invoice_total' => '0.00', 'invoice_paid' => '0.00', 'invoice_balance' => '0.00',
        ])
        // A zero total is not paid, unless the invoice is a credit invoice.
        ->and(calcInvoiceState($id))->toBe(['invoice_status_id' => 2, 'is_read_only' => null, 'payment_method' => 0]);
});

it('marks a credit invoice with a zero balance paid even at a zero total', function () {
    $parent = calcInvoice();
    $credit = calcInvoice(['creditinvoice_parent_id' => $parent, 'invoice_status_id' => 4, 'invoice_number' => '2']);

    app(InvoiceCalculator::class)->recalculateInvoice($credit);

    expect(calcInvoiceState($credit))->toBe(['invoice_status_id' => 4, 'is_read_only' => 1, 'payment_method' => 0]);
});

it('leaves the status alone when the invoice has lost its client', function () {
    $id = calcInvoice();
    calcItem($id, '1', '10.00');
    calcPayment($id, '10.00');
    DB::table('ip_clients')->delete();

    calcRun($id);

    // Mdl_invoices::get_by_id() joins the client, so there is no invoice to mark paid.
    expect(calcInvoiceAmounts($id))->toMatchArray(['invoice_balance' => '0.00'])
        ->and(calcInvoiceState($id))->toBe(['invoice_status_id' => 2, 'is_read_only' => null, 'payment_method' => 0]);
});
