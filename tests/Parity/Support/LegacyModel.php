<?php

namespace Tests\Parity\Support;

use Illuminate\Database\Connection;

/**
 * Legacy v1.7.2 refuses every delete through Admin_Controller::ensure_valid_post_request() (see
 * known-differences.md). These apply the deletes its models would have made, as SQL to the legacy
 * database, so a scenario can still diff the two end states. Written out here on purpose, apart
 * from App\Services\OrphanCleaner, which the scenarios check.
 */
final class LegacyModel
{
    /** Mdl_clients::delete(): MY_Model::delete() (a DELETE by primary key), then delete_orphans(). */
    public static function deleteClient(Connection $db, int $clientId): void
    {
        $db->statement('DELETE FROM ip_clients WHERE ip_clients.client_id = ?', [$clientId]);
        self::deleteOrphans($db);
    }

    /** Mdl_invoices::delete() (Mdl_invoices.php:568-574): MY_Model::delete(), then delete_orphans(). */
    public static function deleteInvoice(Connection $db, int $invoiceId): void
    {
        $db->statement('DELETE FROM ip_invoices WHERE ip_invoices.invoice_id = ?', [$invoiceId]);
        self::deleteOrphans($db);
    }

    /** Mdl_invoice_tax_rates::delete(), MY_Model::delete(); Invoices::delete_invoice_tax() recalculates afterwards. */
    public static function deleteInvoiceTaxRate(Connection $db, int $invoiceTaxRateId): void
    {
        $db->statement('DELETE FROM ip_invoice_tax_rates WHERE ip_invoice_tax_rates.invoice_tax_rate_id = ?', [$invoiceTaxRateId]);
    }

    /** helpers/orphan_helper.php delete_orphans() (:24-41), in its order. */
    public static function deleteOrphans(Connection $db): void
    {
        foreach ([
            'ip_invoices' => ['client_id', 'ip_clients'], 'ip_quotes' => ['client_id', 'ip_clients'], 'ip_invoice_amounts' => ['invoice_id', 'ip_invoices'],
            'ip_quote_amounts' => ['quote_id', 'ip_quotes'], 'ip_payments' => ['invoice_id', 'ip_invoices'], 'ip_client_custom' => ['client_id', 'ip_clients'],
            'ip_invoice_custom' => ['invoice_id', 'ip_invoices'], 'ip_user_custom' => ['user_id', 'ip_users'], 'ip_payment_custom' => ['payment_id', 'ip_payments'],
            'ip_quote_custom' => ['quote_id', 'ip_quotes'], 'ip_invoice_items' => ['invoice_id', 'ip_invoices'], 'ip_invoice_item_amounts' => ['item_id', 'ip_invoice_items'],
            'ip_quote_items' => ['quote_id', 'ip_quotes'], 'ip_quote_item_amounts' => ['item_id', 'ip_quote_items'], 'ip_client_notes' => ['client_id', 'ip_clients'],
            'ip_quote_tax_rates' => ['quote_id', 'ip_quotes'], 'ip_invoice_tax_rates' => ['invoice_id', 'ip_invoices'], 'ip_invoices_recurring' => ['invoice_id', 'ip_invoices'],
        ] as $table => [$column, $parent]) {
            $db->statement("DELETE FROM $table WHERE $column NOT IN (SELECT $column FROM $parent)");
        }
    }
}
