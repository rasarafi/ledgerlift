<?php

use App\Services\OrphanCleaner;
use Illuminate\Support\Facades\DB;

/*
 * OrphanCleaner::run() runs the 18 DELETE statements of delete_orphans() (orphan_helper.php:24-41)
 * in that order. There are no foreign keys, so each test plants a row whose parent is missing
 * and one whose parent exists, runs the cleaner, and checks that exactly the first one is gone.
 */

/** Each child table, the column pointing at its parent and the parent table. */
const ORPHAN_KINDS = [
    'ip_invoices' => ['client_id', 'ip_clients'],
    'ip_quotes' => ['client_id', 'ip_clients'],
    'ip_invoice_amounts' => ['invoice_id', 'ip_invoices'],
    'ip_quote_amounts' => ['quote_id', 'ip_quotes'],
    'ip_payments' => ['invoice_id', 'ip_invoices'],
    'ip_client_custom' => ['client_id', 'ip_clients'],
    'ip_invoice_custom' => ['invoice_id', 'ip_invoices'],
    'ip_user_custom' => ['user_id', 'ip_users'],
    'ip_payment_custom' => ['payment_id', 'ip_payments'],
    'ip_quote_custom' => ['quote_id', 'ip_quotes'],
    'ip_invoice_items' => ['invoice_id', 'ip_invoices'],
    'ip_invoice_item_amounts' => ['item_id', 'ip_invoice_items'],
    'ip_quote_items' => ['quote_id', 'ip_quotes'],
    'ip_quote_item_amounts' => ['item_id', 'ip_quote_items'],
    'ip_client_notes' => ['client_id', 'ip_clients'],
    'ip_quote_tax_rates' => ['quote_id', 'ip_quotes'],
    'ip_invoice_tax_rates' => ['invoice_id', 'ip_invoices'],
    'ip_invoices_recurring' => ['invoice_id', 'ip_invoices'],
];

/** Inserts a row of `$table` pointing at `$parentId`, filling every other column from its default. */
function orphanRow(string $table, string $column, int $parentId): void
{
    // Invoices carry a unique URL key.
    $unique = $table === 'ip_invoices' ? ['invoice_url_key' => md5($table.$parentId.microtime())] : [];

    DB::table($table)->insert([$column => $parentId] + $unique);
}

/** Row 1 of a parent table, with the ancestors that keep it from being an orphan itself. */
function liveParent(string $table): void
{
    if ($table === 'ip_users') {
        return; // The admin, user 1, is in the baseline.
    }

    $grandparents = [
        'ip_invoices' => ['invoice_id', 'client_id', 'ip_clients'], 'ip_quotes' => ['quote_id', 'client_id', 'ip_clients'],
        'ip_payments' => ['payment_id', 'invoice_id', 'ip_invoices'], 'ip_invoice_items' => ['item_id', 'invoice_id', 'ip_invoices'],
        'ip_quote_items' => ['item_id', 'quote_id', 'ip_quotes'],
    ];

    if (! isset($grandparents[$table])) {
        DB::table($table)->insert(['client_id' => 1]);

        return;
    }

    [$key, $column, $grandparent] = $grandparents[$table];
    liveParent($grandparent);
    orphanRow($table, $column, 1);
    DB::table($table)->update([$key => 1]);
}

it('deletes the rows of each kind whose parent is missing, and keeps the others', function (string $table, string $column, string $parentTable) {
    liveParent($parentTable);

    orphanRow($table, $column, 1);
    orphanRow($table, $column, 999);
    expect(DB::table($table)->count())->toBe(2);

    app(OrphanCleaner::class)->run();

    expect(DB::table($table)->pluck($column)->all())->toBe([1]);
})->with(function () {
    foreach (ORPHAN_KINDS as $table => [$column, $parentTable]) {
        yield $table => [$table, $column, $parentTable];
    }
});

it('follows the chain of orphans created by deleting a client, in statement order', function () {
    DB::table('ip_clients')->insert(['client_id' => 1, 'client_name' => 'Kept']);

    // Client 2 is already deleted: its invoice and quote, and everything hanging off them.
    DB::table('ip_invoices')->insert(['invoice_id' => 20, 'client_id' => 2, 'invoice_url_key' => 'k20']);
    DB::table('ip_invoices')->insert(['invoice_id' => 10, 'client_id' => 1, 'invoice_url_key' => 'k10']);
    DB::table('ip_quotes')->insert(['quote_id' => 30, 'client_id' => 2]);
    DB::table('ip_quote_items')->insert(['item_id' => 31, 'quote_id' => 30, 'item_tax_rate_id' => 0]);
    DB::table('ip_quote_item_amounts')->insert(['item_id' => 31]);
    DB::table('ip_invoice_items')->insert(['item_id' => 21, 'invoice_id' => 20]);
    DB::table('ip_invoice_item_amounts')->insert(['item_id' => 21]);
    DB::table('ip_payments')->insert(['payment_id' => 22, 'invoice_id' => 20]);
    DB::table('ip_payment_custom')->insert(['payment_id' => 22]);

    app(OrphanCleaner::class)->run();

    foreach (['ip_invoice_items', 'ip_invoice_item_amounts', 'ip_quotes', 'ip_quote_items', 'ip_quote_item_amounts', 'ip_payments', 'ip_payment_custom'] as $table) {
        expect(DB::table($table)->count())->toBe(0, $table);
    }
    expect(DB::table('ip_invoices')->pluck('invoice_id')->all())->toBe([10]);
});

it('leaves ip_user_clients alone, as delete_orphans() does not list it', function () {
    DB::table('ip_user_clients')->insert(['user_id' => 1, 'client_id' => 404]);

    app(OrphanCleaner::class)->run();

    expect(DB::table('ip_user_clients')->count())->toBe(1);
});
