<?php

use App\Services\InvoiceCalculator;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\Parity\Support\AppClient;
use Tests\Parity\Support\ParityDatabases;

/*
 * InvoiceCalculator against legacy's own recalculation. Each scenario inserts the same invoice
 * rows into both databases. Legacy then recalculates through its endpoints (invoices/ajax/save,
 * payments/ajax/add, invoices/ajax/delete_item). Ledgerlift has no such endpoints yet, so
 * its side runs the same sequence in process against NEW_DB: InvoiceCalculator for the maths, and
 * plain SQL for the rows those endpoints write around it. Both databases are then diffed whole, and
 * the stored amounts checked against the values worked by hand.
 * Fixture tax rates: 1 VAT 15.00, 2 Reduced 5.00, 3 Service 7.50; client 1, admin user 1, group 3.
 */

/** The brief's invoice: VAT on A, Reduced and a per-unit discount on B, no tax on C. */
const CALC_THREE_ITEMS = [
    ['item_id' => 1, 'item_name' => 'Workshop fit-out', 'item_quantity' => '1', 'item_price' => '1200.00', 'item_tax_rate_id' => 1],
    ['item_id' => 2, 'item_name' => 'Hinges', 'item_quantity' => '12', 'item_price' => '19.99', 'item_tax_rate_id' => 2, 'item_discount_amount' => '1.50'],
    ['item_id' => 3, 'item_name' => 'Labour', 'item_quantity' => '3.5', 'item_price' => '45.00', 'item_tax_rate_id' => 0],
];

const CALC_THREE_ITEM_AMOUNTS = [
    'items' => [
        1 => ['item_subtotal' => '1200.00', 'item_tax_total' => '180.00', 'item_discount' => '0.00', 'item_total' => '1380.00'],
        2 => ['item_subtotal' => '239.88', 'item_tax_total' => '11.99', 'item_discount' => '18.00', 'item_total' => '233.87'],
        3 => ['item_subtotal' => '157.50', 'item_tax_total' => '0.00', 'item_discount' => '0.00', 'item_total' => '157.50'],
    ],
    'invoices' => [1 => [
        'invoice_item_subtotal' => '1579.38', 'invoice_item_tax_total' => '191.99', 'invoice_tax_total' => '132.85',
        'invoice_total' => '1713.80', 'invoice_paid' => '0.00', 'invoice_balance' => '1713.80',
        'invoice_status_id' => 2, 'is_read_only' => null, 'payment_method' => 0,
    ]],
    'invoice taxes' => [1 => '132.85'],
];

/**
 * An invoice as Mdl_invoices::create() leaves it (with an empty amounts row), its items and its
 * invoice taxes (tax rate id => include_item_tax).
 *
 * @param  list<array<string, mixed>>  $items
 * @param  array<int, int>  $taxes
 */
function calcParityInvoice(Connection $db, int $id, array $items, array $taxes = []): void
{
    $db->table('ip_invoices')->insert([
        'invoice_id' => $id, 'user_id' => 1, 'client_id' => 1, 'invoice_group_id' => 3, 'invoice_status_id' => 2, 'invoice_number' => (string) $id,
        'invoice_date_created' => '2026-10-01', 'invoice_time_created' => '09:00:00', 'invoice_date_modified' => '2026-10-01 09:00:00',
        'invoice_date_due' => '2026-10-31', 'invoice_terms' => '', 'invoice_url_key' => str_pad((string) $id, 32, 'k'),
    ]);
    $db->table('ip_invoice_amounts')->insert(['invoice_id' => $id]);

    foreach ($items as $order => $item) {
        $db->table('ip_invoice_items')->insert($item + [
            'invoice_id' => $id, 'item_date_added' => '2026-10-01', 'item_description' => '', 'item_order' => $order + 1,
        ]);
    }

    foreach ($taxes as $taxRateId => $includeItemTax) {
        $db->table('ip_invoice_tax_rates')->insert(['invoice_id' => $id, 'tax_rate_id' => $taxRateId, 'include_item_tax' => $includeItemTax]);
    }
}

/**
 * Legacy's Save button (views/invoices/view.php:72-104): the invoice form and its items as JSON,
 * posted to invoices/ajax/save. The items are posted as stored, so their rows stay as they are.
 */
function calcLegacySave(AppClient $app, Connection $db, int $invoiceId, string $discountAmount = '', string $discountPercent = ''): void
{
    $items = $db->table('ip_invoice_items')->where('invoice_id', $invoiceId)->orderBy('item_order')->get()->map(fn (object $item) => [
        'invoice_id' => (string) $invoiceId, 'item_id' => (string) $item->item_id, 'item_name' => $item->item_name, 'item_description' => $item->item_description,
        'item_quantity' => $item->item_quantity, 'item_price' => $item->item_price, 'item_discount_amount' => (string) $item->item_discount_amount,
        'item_tax_rate_id' => (string) $item->item_tax_rate_id, 'item_product_id' => '', 'item_task_id' => '', 'item_product_unit_id' => '0',
        'item_order' => $item->item_order,
    ])->all();

    $response = $app->post('invoices/ajax/save', [
        'legacy_calculation' => '1', 'invoice_id' => (string) $invoiceId, 'invoice_number' => (string) $invoiceId,
        'invoice_date_created' => '10/01/2026', 'invoice_date_due' => '10/31/2026', 'invoice_status_id' => '2', 'invoice_password' => '',
        'items' => json_encode($items), 'invoice_discount_amount' => $discountAmount, 'invoice_discount_percent' => $discountPercent,
        'invoice_terms' => '', 'payment_method' => '0',
    ], ajax: true);

    expect($response->json('success'))->toBe(1, "invoices/ajax/save failed: {$response->body()}");
}

/** Runs `$run` with the app's default connection (and so Settings and the calculator) on NEW_DB. */
function calcOnNewDatabase(Closure $run): void
{
    $connection = (string) config('database.default');
    $database = config("database.connections.$connection.database");

    config(["database.connections.$connection.database" => ParityDatabases::database('new')]);
    DB::purge($connection);
    app()->forgetScopedInstances();

    try {
        $run(app(InvoiceCalculator::class));
    } finally {
        config(["database.connections.$connection.database" => $database]);
        DB::purge($connection);
        app()->forgetScopedInstances();
    }
}

/** What invoices/ajax/save does on legacy, in its order (Ajax.php:21-187), for an unchanged invoice and items. */
function calcNewSave(int $invoiceId, string $discountAmount = '', string $discountPercent = ''): void
{
    calcOnNewDatabase(function (InvoiceCalculator $calculator) use ($invoiceId, $discountAmount, $discountPercent) {
        // Ajax.php:69-96: each item is re-saved as posted (unchanged), and Mdl_items::save() (:103-120)
        // recalculates its amounts and then the invoice's, still with the stored discounts.
        foreach (DB::table('ip_invoice_items')->where('invoice_id', $invoiceId)->orderBy('item_order')->pluck('item_id') as $itemId) {
            $calculator->recalculateItem((int) $itemId);
            $calculator->recalculateInvoice($invoiceId);
        }

        // Ajax.php:37-38 and 147-164: the posted discounts, cast with (float), and MY_Model::save()'s
        // invoice_date_modified. The other posted fields equal the stored ones.
        DB::table('ip_invoices')->where('invoice_id', $invoiceId)->update([
            'invoice_discount_amount' => (string) (float) $discountAmount,
            'invoice_discount_percent' => (string) (float) $discountPercent,
            'invoice_date_modified' => date('Y-m-d H:i:s'),
        ]);

        // Ajax.php:183-187: the trailing recalculation that picks up the new discounts.
        $calculator->recalculateInvoice($invoiceId);
    });
}

function calcLegacyPayment(AppClient $app, int $invoiceId, string $amount, string $methodId): void
{
    $response = $app->post('payments/ajax/add', [
        'invoice_id' => (string) $invoiceId, 'payment_amount' => $amount, 'payment_method_id' => $methodId, 'payment_date' => '10/15/2026', 'payment_note' => '',
    ], ajax: true);

    expect($response->json('success'))->toBe(1, "payments/ajax/add failed: {$response->body()}");
}

/** What Mdl_payments::save() does on legacy (Mdl_payments.php:122-156) for a new payment. */
function calcNewPayment(int $invoiceId, string $amount, string $methodId): void
{
    calcOnNewDatabase(function (InvoiceCalculator $calculator) use ($invoiceId, $amount, $methodId) {
        DB::table('ip_payments')->insert([
            'invoice_id' => $invoiceId, 'payment_method_id' => $methodId, 'payment_date' => '2026-10-15', 'payment_amount' => $amount, 'payment_note' => '',
        ]);
        $calculator->recalculateInvoice($invoiceId);

        $stored = DB::table('ip_invoice_amounts')->where('invoice_id', $invoiceId)->first();

        if ((float) $stored?->invoice_paid >= (float) $stored?->invoice_total) {
            DB::table('ip_invoices')->where('invoice_id', $invoiceId)->update(['invoice_status_id' => 4]);
        }

        $calculator->recalculateInvoice($invoiceId);
    });
}

/** The stored amounts: per item, per invoice (with its status), and per invoice tax. */
function calcStoredAmounts(Connection $db): array
{
    return [
        'items' => $db->table('ip_invoice_item_amounts')->orderBy('item_id')
            ->get(['item_id', 'item_subtotal', 'item_tax_total', 'item_discount', 'item_total'])
            ->mapWithKeys(fn (object $row) => [$row->item_id => array_diff_key((array) $row, ['item_id' => 1])])->all(),
        'invoices' => $db->table('ip_invoices')->join('ip_invoice_amounts', 'ip_invoice_amounts.invoice_id', '=', 'ip_invoices.invoice_id')
            ->orderBy('ip_invoices.invoice_id')
            ->get([
                'ip_invoices.invoice_id', 'invoice_item_subtotal', 'invoice_item_tax_total', 'invoice_tax_total', 'invoice_total', 'invoice_paid', 'invoice_balance',
                'invoice_status_id', 'is_read_only', 'payment_method',
            ])
            ->mapWithKeys(fn (object $row) => [$row->invoice_id => array_diff_key((array) $row, ['invoice_id' => 1])])->all(),
        'invoice taxes' => $db->table('ip_invoice_tax_rates')->orderBy('invoice_tax_rate_id')->pluck('invoice_tax_rate_amount', 'invoice_tax_rate_id')->all(),
    ];
}

it('recalculates the three-item invoice with an invoice tax and a 10% discount as legacy does', function () {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        calcParityInvoice($db, 1, CALC_THREE_ITEMS, [3 => 1]);

        if ($side === 'legacy') {
            $app->login();
            calcLegacySave($app, $db, 1, discountPercent: '10');
        } else {
            calcNewSave(1, discountPercent: '10');
        }

        return calcStoredAmounts($db);
    });

    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy)->toBe(CALC_THREE_ITEM_AMOUNTS);
});

it('applies a discount amount and a discount percent together as legacy does', function () {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        calcParityInvoice($db, 1, [
            ['item_id' => 1, 'item_name' => 'Consulting', 'item_quantity' => '1', 'item_price' => '100.00', 'item_tax_rate_id' => 0],
            ['item_id' => 2, 'item_name' => 'Placeholder', 'item_quantity' => '1', 'item_price' => '0.00', 'item_tax_rate_id' => 0],
        ]);

        // Ajax::save() keeps only the percent when both are posted (Ajax.php:41-43), and legacy's
        // invoices/recalculate_all_invoices refuses every POST (ensure_valid_post_request(), see
        // known-differences.md). So the save posts no discount, both are stored by SQL, and deleting
        // the placeholder item (invoices/ajax/delete_item, Mdl_items::delete()) recalculates with them.
        if ($side === 'legacy') {
            $app->login();
            calcLegacySave($app, $db, 1);
        } else {
            calcNewSave(1);
        }

        $db->table('ip_invoices')->where('invoice_id', 1)->update(['invoice_discount_amount' => '5.00', 'invoice_discount_percent' => '2.50']);

        if ($side === 'legacy') {
            expect($app->post('invoices/ajax/delete_item/1', ['item_id' => '2'], ajax: true)->json('success'))->toBe(1);
        } else {
            // Mdl_items::delete() (Mdl_items.php:125-152): the item, its amounts, then the invoice recalculated.
            calcOnNewDatabase(function (InvoiceCalculator $calculator) {
                DB::table('ip_invoice_items')->where('item_id', 2)->delete();
                DB::table('ip_invoice_item_amounts')->where('item_id', 2)->delete();
                $calculator->recalculateInvoice(1);
            });
        }

        return calcStoredAmounts($db)['invoices'];
    });

    // 100 − 5 = 95; round(95 / 100 × 2.5, 2) = 2.38; 95 − 2.38 = 92.62.
    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy[1])->toMatchArray(['invoice_item_subtotal' => '100.00', 'invoice_total' => '92.62', 'invoice_balance' => '92.62']);
});

it('rounds the stored amounts on write as legacy does', function () {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        calcParityInvoice($db, 1, [['item_id' => 1, 'item_name' => 'Cable', 'item_quantity' => '0.333', 'item_price' => '10.00', 'item_tax_rate_id' => 1]]);
        calcParityInvoice($db, 2, [['item_id' => 2, 'item_name' => 'Washers', 'item_quantity' => '3', 'item_price' => '0.35', 'item_tax_rate_id' => 3]]);

        if ($side === 'legacy') {
            $app->login();
            calcLegacySave($app, $db, 1);
            calcLegacySave($app, $db, 2);
        } else {
            calcNewSave(1);
            calcNewSave(2);
        }

        return calcStoredAmounts($db);
    });

    // 0.333 × 10 = 3.33, tax 0.4995 → 0.50, total 3.8295 → 3.83; 3 × 0.35 = 1.05, tax 0.07875 → 0.08, total 1.12875 → 1.13.
    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy['items'])->toBe([
            1 => ['item_subtotal' => '3.33', 'item_tax_total' => '0.50', 'item_discount' => '0.00', 'item_total' => '3.83'],
            2 => ['item_subtotal' => '1.05', 'item_tax_total' => '0.08', 'item_discount' => '0.00', 'item_total' => '1.13'],
        ])
        ->and($result->legacy['invoices'][1])->toMatchArray(['invoice_item_subtotal' => '3.33', 'invoice_item_tax_total' => '0.50', 'invoice_total' => '3.83'])
        ->and($result->legacy['invoices'][2])->toMatchArray(['invoice_item_subtotal' => '1.05', 'invoice_item_tax_total' => '0.08', 'invoice_total' => '1.13']);
});

it('takes two payments to a zero balance, then paid and read-only, as legacy does', function () {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        calcParityInvoice($db, 1, CALC_THREE_ITEMS, [3 => 1]);
        $steps = [];

        if ($side === 'legacy') {
            $app->login();
            calcLegacySave($app, $db, 1, discountPercent: '10');
            calcLegacyPayment($app, 1, '500.00', '3');
            $steps['after 500.00'] = calcStoredAmounts($db)['invoices'][1];
            calcLegacyPayment($app, 1, '1213.80', '0');
        } else {
            calcNewSave(1, discountPercent: '10');
            calcNewPayment(1, '500.00', '3');
            $steps['after 500.00'] = calcStoredAmounts($db)['invoices'][1];
            calcNewPayment(1, '1213.80', '0');
        }

        $steps['after 1213.80'] = calcStoredAmounts($db)['invoices'][1];

        return $steps;
    });

    // Mdl_payments::save() recalculates twice: the first sets status 4 (from 2), the second sees 4 and sets read-only.
    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy['after 500.00'])->toMatchArray([
            'invoice_total' => '1713.80', 'invoice_paid' => '500.00', 'invoice_balance' => '1213.80', 'invoice_status_id' => 2, 'is_read_only' => null, 'payment_method' => 0,
        ])
        ->and($result->legacy['after 1213.80'])->toMatchArray([
            'invoice_total' => '1713.80', 'invoice_paid' => '1713.80', 'invoice_balance' => '0.00', 'invoice_status_id' => 4, 'is_read_only' => 1, 'payment_method' => 3,
        ]);
});

it('rounds a discount percent where PHP 8.2 and 8.4 differ as legacy does', function () {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        calcParityInvoice($db, 1, [['item_id' => 1, 'item_name' => 'Survey', 'item_quantity' => '1', 'item_price' => '2150.00', 'item_tax_rate_id' => 0]]);

        if ($side === 'legacy') {
            $app->login();
            calcLegacySave($app, $db, 1, discountPercent: '43.87');
        } else {
            calcNewSave(1, discountPercent: '43.87');
        }

        return calcStoredAmounts($db)['invoices'];
    });

    // 2150 / 100 × 43.87 = 943.20499999999993 → 943.21 on PHP 8.2 (943.20 with PHP 8.4's round()); 2150 − 943.21.
    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy[1])->toMatchArray(['invoice_total' => '1206.79', 'invoice_balance' => '1206.79']);
});
