<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * helpers/orphan_helper.php::delete_orphans(): there are no foreign keys, so deleting a client,
 * invoice or payment leaves its children behind and these statements sweep them up. The order
 * matters (invoices go before their amounts, items and payments, which go before theirs).
 * ip_user_clients is not in the list: legacy leaves a deleted client's assignments behind.
 */
final class OrphanCleaner
{
    private const STATEMENTS = [
        'DELETE FROM ip_invoices WHERE client_id NOT IN (SELECT client_id FROM ip_clients)', // orphan_helper.php:24
        'DELETE FROM ip_quotes WHERE client_id NOT IN (SELECT client_id FROM ip_clients)', // :25
        'DELETE FROM ip_invoice_amounts WHERE invoice_id NOT IN (SELECT invoice_id FROM ip_invoices)', // :26
        'DELETE FROM ip_quote_amounts WHERE quote_id NOT IN (SELECT quote_id FROM ip_quotes)', // :27
        'DELETE FROM ip_payments WHERE invoice_id NOT IN (SELECT invoice_id FROM ip_invoices)', // :28
        'DELETE FROM ip_client_custom WHERE client_id NOT IN (SELECT client_id FROM ip_clients)', // :29
        'DELETE FROM ip_invoice_custom WHERE invoice_id NOT IN (SELECT invoice_id FROM ip_invoices)', // :30
        'DELETE FROM ip_user_custom WHERE user_id NOT IN (SELECT user_id FROM ip_users)', // :31
        'DELETE FROM ip_payment_custom WHERE payment_id NOT IN (SELECT payment_id FROM ip_payments)', // :32
        'DELETE FROM ip_quote_custom WHERE quote_id NOT IN (SELECT quote_id FROM ip_quotes)', // :33
        'DELETE FROM ip_invoice_items WHERE invoice_id NOT IN (SELECT invoice_id FROM ip_invoices)', // :34
        'DELETE FROM ip_invoice_item_amounts WHERE item_id NOT IN (SELECT item_id FROM ip_invoice_items)', // :35
        'DELETE FROM ip_quote_items WHERE quote_id NOT IN (SELECT quote_id FROM ip_quotes)', // :36
        'DELETE FROM ip_quote_item_amounts WHERE item_id NOT IN (SELECT item_id FROM ip_quote_items)', // :37
        'DELETE FROM ip_client_notes WHERE client_id NOT IN (SELECT client_id FROM ip_clients)', // :38
        'DELETE FROM ip_quote_tax_rates WHERE quote_id NOT IN (SELECT quote_id FROM ip_quotes)', // :39
        'DELETE FROM ip_invoice_tax_rates WHERE invoice_id NOT IN (SELECT invoice_id FROM ip_invoices)', // :40
        'DELETE FROM ip_invoices_recurring WHERE invoice_id NOT IN (SELECT invoice_id FROM ip_invoices)', // :41
    ];

    public function run(): void
    {
        foreach (self::STATEMENTS as $statement) {
            DB::delete($statement);
        }
    }
}
