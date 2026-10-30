<?php

namespace App\Http\Controllers;

use App\Http\Responses\LegacyRedirect;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Support\LegacyCrypt;
use App\Support\LegacyPager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** modules/invoices/controllers/Invoices.php */
class InvoiceController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return LegacyRedirect::to($request, url('invoices/status/all'));
    }

    /** Invoices::status(): `all` and any status not listed filter nothing. */
    public function status(string $status = '', string $offset = '0'): View
    {
        $query = Invoice::query()->listing();
        $filter = $status === '' ? 'all' : $status;

        match ($filter) {
            'draft' => $query->where('ip_invoices.invoice_status_id', 1),
            'sent' => $query->where('ip_invoices.invoice_status_id', 2),
            'viewed' => $query->where('ip_invoices.invoice_status_id', 3),
            // Mdl_invoices::is_paid(): paid, or settled whatever the status.
            'paid' => $query->where('ip_invoices.invoice_status_id', 4)->orWhere('ip_invoice_amounts.invoice_balance', '0.00'),
            'overdue' => $query->havingRaw('is_overdue = 1'),
            default => null,
        };

        $pager = new LegacyPager((int) $offset, LegacyPager::defaultPerPage(), (clone $query)->toBase()->getCountForPagination());

        return view('invoices.index', [
            'invoices' => $query->offset($pager->offset)->limit($pager->perPage)->get(),
            'invoiceStatuses' => Invoice::statuses(),
            'pager' => $pager,
            'status' => $filter,
            // The pager is built from the third URI segment, as typed ('' when absent).
            'statusSegment' => $status,
            'filterDisplay' => true,
            'filterPlaceholder' => __('ip.filter_invoices'),
            'filterMethod' => 'filter_invoices',
        ]);
    }

    /** Invoices::view() (Invoices.php:120-228), without custom fields and e-invoicing (not ported). */
    public function view(Request $request, LegacyCrypt $crypt, string $id): View
    {
        $invoice = Invoice::query()->detail()->where('ip_invoices.invoice_id', $id)->first() ?? abort(404);
        $invoice->invoice_password = $crypt->decryptPassword($invoice->invoice_password);

        return view('invoices.view', [
            'invoice' => $invoice,
            // The record's own id, never the URL segment, goes into the page's scripts.
            'invoiceId' => (int) $invoice->invoice_id,
            // Mdl_items: amounts, tax rate and product left joined, by item_order.
            'items' => DB::table('ip_invoice_items')
                ->leftJoin('ip_invoice_item_amounts', 'ip_invoice_item_amounts.item_id', '=', 'ip_invoice_items.item_id')
                ->leftJoin('ip_tax_rates AS item_tax_rates', 'item_tax_rates.tax_rate_id', '=', 'ip_invoice_items.item_tax_rate_id')
                ->where('ip_invoice_items.invoice_id', $id)
                ->orderBy('ip_invoice_items.item_order')
                ->get([
                    'ip_invoice_item_amounts.item_subtotal', 'ip_invoice_item_amounts.item_tax_total', 'ip_invoice_item_amounts.item_discount',
                    'ip_invoice_item_amounts.item_total', 'ip_invoice_items.*',
                    'item_tax_rates.tax_rate_percent AS item_tax_rate_percent', 'item_tax_rates.tax_rate_name AS item_tax_rate_name',
                ]),
            // Invoices.php:191-192: the user can be changed when more than one admin is active.
            'changeUser' => DB::table('ip_users')->where(['user_type' => 1, 'user_active' => 1])->sum('user_type') > 1,
            'itsMine' => $request->user()?->getAuthIdentifier() == $invoice->user_id,
            'invoiceUser' => DB::table('ip_users')->where('user_id', $invoice->user_id)->first(),
            'taxRates' => DB::table('ip_tax_rates')->orderBy('tax_rate_percent')->get(),
            'invoiceTaxRates' => DB::table('ip_invoice_tax_rates')
                ->join('ip_tax_rates', 'ip_tax_rates.tax_rate_id', '=', 'ip_invoice_tax_rates.tax_rate_id')
                ->where('ip_invoice_tax_rates.invoice_id', $id)
                ->get(['ip_tax_rates.tax_rate_name AS invoice_tax_rate_name', 'ip_tax_rates.tax_rate_percent AS invoice_tax_rate_percent', 'ip_invoice_tax_rates.*']),
            'units' => DB::table('ip_units')->orderBy('unit_name')->get(),
            'paymentMethods' => DB::table('ip_payment_methods')->get(),
            'invoiceStatuses' => Invoice::statuses(),
            // Mdl_invoices::get_parent_invoice_number(), for the credit invoice's link to its invoice.
            'parentInvoiceNumber' => $invoice->invoice_sign == -1
                ? Invoice::query()->listing()->where('ip_invoices.invoice_id', $invoice->creditinvoice_parent_id)->value('ip_invoices.invoice_number')
                : null,
            'paymentCfExist' => DB::table('ip_custom_fields')->where('custom_field_table', 'ip_payment_custom')->exists() ? 'yes' : 'no',
        ]);
    }

    /** Invoices::delete(): Mdl_invoices::delete() flashes the deletion (Response_Model). */
    public function delete(Request $request, InvoiceService $invoices, string $id): RedirectResponse
    {
        abort_unless($this->found($id), 404);

        $redirect = LegacyRedirect::to($request, url('invoices/index'));

        return $invoices->delete((int) $id)
            ? $redirect->with('alert_success', __('ip.record_successfully_deleted'))
            : $redirect->with('alert_error', __('ip.invoice_deletion_forbidden'));
    }

    /** Invoices::delete_invoice_tax(): Mdl_invoice_tax_rates::delete() flashes the deletion. */
    public function deleteInvoiceTax(Request $request, InvoiceService $invoices, string $invoiceId, string $invoiceTaxRateId): RedirectResponse
    {
        $invoices->deleteTaxRate((int) $invoiceId, (int) $invoiceTaxRateId);

        return LegacyRedirect::to($request, url('invoices/view/'.$invoiceId))->with('alert_success', __('ip.record_successfully_deleted'));
    }

    /** Mdl_invoices::get_by_id(): the invoice with its client and user. */
    private function found(string $id): bool
    {
        return Invoice::query()->listing()->where('ip_invoices.invoice_id', $id)->exists();
    }
}
