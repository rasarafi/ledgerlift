<?php

namespace App\Services;

use App\Support\LegacyMath;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * The invoice maths of legacy InvoicePlane with LEGACY_CALCULATION=true (config.php:21; the
 * only mode ported, as `einvoicing` stays unset): Mdl_item_amounts::calculate() and
 * Mdl_invoice_amounts::calculate(), step for step.
 *
 * Fidelity rules: the maths is PHP floats in legacy's operation order; a float is written as
 * `(string) $float` (precision 14, as CodeIgniter's query builder inlines it) and MySQL's
 * DECIMAL columns round it on write; stored values are re-read wherever legacy re-reads them.
 */
final class InvoiceCalculator
{
    public function __construct(private readonly Settings $settings) {}

    /** Mdl_item_amounts::calculate() (Mdl_item_amounts.php:29-72), legacy branch. */
    public function recalculateItem(int $itemId): void
    {
        // :31-32 Mdl_items::get_by_id(): the item with its tax rate's percent, a left join (Mdl_items.php:25-42),
        // so an item without a rate has a NULL percent. A missing item reads as all NULL, as on legacy.
        $item = DB::table('ip_invoice_items')
            ->leftJoin('ip_tax_rates AS item_tax_rates', 'item_tax_rates.tax_rate_id', '=', 'ip_invoice_items.item_tax_rate_id')
            ->where('ip_invoice_items.item_id', $itemId)
            ->first([
                'ip_invoice_items.item_quantity', 'ip_invoice_items.item_price', 'ip_invoice_items.item_discount_amount',
                'item_tax_rates.tax_rate_percent AS item_tax_rate_percent',
            ]);

        $quantity = (float) $item?->item_quantity;

        $subtotal = $quantity * (float) $item?->item_price; // :34
        $taxTotal = $subtotal * ((float) $item?->item_tax_rate_percent / 100); // :38, tax on the gross subtotal
        $discountTotal = (float) $item?->item_discount_amount * $quantity; // :39, the discount is per unit
        $total = $subtotal + $taxTotal - $discountTotal; // :40

        $amounts = [
            'item_id' => $itemId,
            'item_subtotal' => (string) $subtotal,
            'item_tax_total' => (string) $taxTotal,
            'item_discount' => (string) $discountTotal,
            'item_total' => (string) $total,
        ];

        // :65-71 update the item's amounts row(s) if there are any, insert one otherwise.
        $query = DB::table('ip_invoice_item_amounts')->where('item_id', $itemId);
        $query->exists() ? $query->update($amounts) : DB::table('ip_invoice_item_amounts')->insert($amounts);
    }

    /**
     * Mdl_invoice_amounts::calculate() (Mdl_invoice_amounts.php:51-134), legacy branch: item sums,
     * calculate_discount(), paid/balance and the upsert, calculate_invoice_taxes(), then the
     * paid status and read-only flag.
     */
    public function recalculateInvoice(int $invoiceId): void
    {
        // :54-65 the stored (already rounded) item amounts, summed. SUM() of no rows is NULL.
        $sums = DB::selectOne(
            'SELECT SUM(item_subtotal) AS invoice_item_subtotal, SUM(item_tax_total) AS invoice_item_tax_total, SUM(item_discount) AS invoice_item_discount
             FROM ip_invoice_item_amounts
             WHERE item_id IN (SELECT item_id FROM ip_invoice_items WHERE invoice_id = ?)',
            [$invoiceId],
        );

        // :69-71 the item subtotal is stored net of the item discounts, then the invoice discounts apply.
        $itemSubtotal = (float) $sums->invoice_item_subtotal - (float) $sums->invoice_item_discount;
        $total = $this->calculateDiscount($invoiceId, $itemSubtotal + (float) $sums->invoice_item_tax_total);

        // :78-83
        $paid = (float) DB::table('ip_payments')->where('invoice_id', $invoiceId)->sum('payment_amount');

        $amounts = [
            'invoice_id' => $invoiceId,
            'invoice_item_subtotal' => (string) $itemSubtotal,
            // :89 written as the SUM() returned it: a 2-dp string, or NULL for an invoice without items.
            'invoice_item_tax_total' => $sums->invoice_item_tax_total,
            'invoice_total' => (string) $total,
            'invoice_paid' => (string) $paid,
            'invoice_balance' => (string) ($total - $paid),
        ];

        // :95-104
        $query = DB::table('ip_invoice_amounts')->where('invoice_id', $invoiceId);
        $query->exists() ? $query->update($amounts) : DB::table('ip_invoice_amounts')->insert($amounts);

        $this->calculateInvoiceTaxes($invoiceId); // :107

        $this->markPaid($invoiceId);
    }

    /**
     * calculate_discount() (:142-154): the total and the invoice's stored discounts are each cut to
     * tax_rate_decimal_places (read once, :26), the amount comes off, then the rounded percent of
     * what is left. With PHP 8.2's rounding (LegacyMath), as legacy runs it.
     */
    private function calculateDiscount(int $invoiceId, float $invoiceTotal): float
    {
        $places = (int) $this->settings->get('tax_rate_decimal_places');
        $invoice = DB::table('ip_invoices')->where('invoice_id', $invoiceId)
            ->first(['invoice_discount_amount', 'invoice_discount_percent']);

        $total = (float) LegacyMath::numberFormat($invoiceTotal, $places, '.', '');
        $discountAmount = (float) LegacyMath::numberFormat((float) $invoice?->invoice_discount_amount, $places, '.', '');
        $discountPercent = (float) LegacyMath::numberFormat((float) $invoice?->invoice_discount_percent, $places, '.', '');

        $total -= $discountAmount;

        return $total - LegacyMath::round($total / 100 * $discountPercent, $places);
    }

    /** calculate_invoice_taxes() (:179-249), legacy branch. */
    private function calculateInvoiceTaxes(int $invoiceId): void
    {
        // :182-184 Mdl_invoice_tax_rates joins its tax rate (Mdl_invoice_tax_rates.php:23-33, an inner join),
        // so a row whose tax rate is gone is neither recalculated here nor counted as "taxes applied".
        $taxRates = DB::table('ip_invoice_tax_rates')
            ->join('ip_tax_rates', 'ip_tax_rates.tax_rate_id', '=', 'ip_invoice_tax_rates.tax_rate_id')
            ->where('ip_invoice_tax_rates.invoice_id', $invoiceId)
            ->get(['ip_invoice_tax_rates.invoice_tax_rate_id', 'ip_invoice_tax_rates.include_item_tax', 'ip_tax_rates.tax_rate_percent AS invoice_tax_rate_percent']);

        if ($taxRates->isEmpty()) {
            // :239-247
            DB::table('ip_invoice_amounts')->where('invoice_id', $invoiceId)->update(['invoice_tax_total' => '0.00']);

            return;
        }

        // :189 the amounts row just written, re-read: the taxes are worked from its rounded values.
        $stored = DB::table('ip_invoice_amounts')->where('invoice_id', $invoiceId)->first();

        foreach ($taxRates as $taxRate) {
            // :193-199
            $base = $taxRate->include_item_tax
                ? (float) $stored?->invoice_item_subtotal + (float) $stored?->invoice_item_tax_total
                : (float) $stored?->invoice_item_subtotal;

            DB::table('ip_invoice_tax_rates')->where('invoice_tax_rate_id', $taxRate->invoice_tax_rate_id)
                ->update(['invoice_tax_rate_amount' => (string) ($base * ((float) $taxRate->invoice_tax_rate_percent / 100))]);
        }

        // :210-216 the tax total is the SUM of the stored DECIMAL(10,2) amounts, every row of the invoice.
        DB::update(
            'UPDATE ip_invoice_amounts SET invoice_tax_total = (SELECT SUM(invoice_tax_rate_amount) FROM ip_invoice_tax_rates WHERE invoice_id = ?) WHERE invoice_id = ?',
            [$invoiceId, $invoiceId],
        );

        // :219-229 re-read once more; the discounts apply again to the total with the taxes.
        $stored = DB::table('ip_invoice_amounts')->where('invoice_id', $invoiceId)->first();
        $total = (float) $stored?->invoice_item_subtotal + (float) $stored?->invoice_item_tax_total + (float) $stored?->invoice_tax_total;
        $total = $this->calculateDiscount($invoiceId, $total);

        DB::table('ip_invoice_amounts')->where('invoice_id', $invoiceId)->update([
            'invoice_total' => (string) $total,
            'invoice_balance' => (string) ($total - (float) $stored?->invoice_paid),
        ]);
    }

    /** Mdl_invoice_amounts::calculate() :109-133: a settled invoice becomes paid, from any status. */
    private function markPaid(int $invoiceId): void
    {
        // :110-111 re-fetched through Mdl_invoices::get_by_id(): inner joins to the client and the user, the
        // amounts from a left join with IFNULL(..., '0.00') (Mdl_invoices.php:92-97, 134-140).
        $invoice = DB::table('ip_invoices')
            ->join('ip_clients', 'ip_clients.client_id', '=', 'ip_invoices.client_id')
            ->join('ip_users', 'ip_users.user_id', '=', 'ip_invoices.user_id')
            ->leftJoin('ip_invoice_amounts', 'ip_invoice_amounts.invoice_id', '=', 'ip_invoices.invoice_id')
            ->where('ip_invoices.invoice_id', $invoiceId)
            ->first([
                'ip_invoices.invoice_status_id', 'ip_invoices.creditinvoice_parent_id',
                DB::raw("IFNULL(ip_invoice_amounts.invoice_total, '0.00') AS invoice_total"),
                DB::raw("IFNULL(ip_invoice_amounts.invoice_balance, '0.00') AS invoice_balance"),
            ]);

        // Legacy reads the properties of a NULL row then: a NULL total is never != 0 and nothing changes.
        if ($invoice === null) {
            return;
        }

        $isCredit = (int) $invoice->creditinvoice_parent_id > 0; // :112

        // :116 tests the stored values, so a float remainder of the balance has already been rounded away.
        if (! ((float) $invoice->invoice_balance == 0 && ((float) $invoice->invoice_total != 0 || $isCredit))) {
            return;
        }

        // :117-119 the first payment's method (no ORDER BY, as legacy), 0 without one.
        $paymentMethodId = DB::table('ip_payments')->where('invoice_id', $invoiceId)->value('payment_method_id') ?: 0;

        DB::table('ip_invoices')->where('invoice_id', $invoiceId)
            ->update(['invoice_status_id' => 4, 'payment_method' => $paymentMethodId]);

        // :125-132 read-only only when the status from before this update equals read_only_toggle, so an
        // invoice that has just become paid turns read-only on the next recalculation (loose ==, as legacy).
        if (! config('ledgerlift.disable_read_only')
            && (string) $invoice->invoice_status_id == (string) $this->settings->get('read_only_toggle', '')) {
            DB::table('ip_invoices')->where('invoice_id', $invoiceId)->update(['is_read_only' => 1]);
        }
    }
}
