<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Family;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Support\LegacyLike;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** modules/filter/controllers/Ajax.php */
class FilterAjaxController extends Controller
{
    /** Ajax::filter_families(): every keyword has to appear in the lower-cased name. */
    public function filterFamilies(Request $request): View
    {
        $families = Family::query()->orderBy('family_name');

        foreach (explode(' ', (string) $request->post('filter_query')) as $keyword) {
            if ($keyword) {
                $families->whereRaw("CONCAT_WS('^',LOWER(family_name)) LIKE ? ESCAPE '!'", ['%'.LegacyLike::escape(mb_strtolower($keyword)).'%']);
            }
        }

        return view('families.partial_families_table', ['rows' => $families->get()]);
    }

    /**
     * Ajax::filter_clients(): every keyword (lower-cased and trimmed) has to appear in the title,
     * name, surname, e-mail, phone or active flag. Unlike the list, it covers inactive clients too.
     */
    public function filterClients(Request $request): View
    {
        $clients = Client::query()->listing()->withInvoiceTotals();

        foreach (explode(' ', (string) $request->post('filter_query')) as $keyword) {
            if ($keyword) {
                $clients->whereRaw(
                    "CONCAT_WS('^',LOWER(client_title),LOWER(client_name),LOWER(client_surname),LOWER(client_email),client_phone,client_active) LIKE ? ESCAPE '!'",
                    ['%'.LegacyLike::escape(mb_trim(mb_strtolower($keyword))).'%'],
                );
            }
        }

        return view('clients.partial_client_table', ['rows' => $clients->get()]);
    }

    /** Ajax::filter_products(): every keyword has to appear in the SKU, family, name, description, price or tariff. */
    public function filterProducts(Request $request): View
    {
        $products = Product::query()->listing();

        foreach (explode(' ', (string) $request->post('filter_query')) as $keyword) {
            if ($keyword) {
                $products->whereRaw(
                    "CONCAT_WS('^',product_sku,LOWER(family_name),LOWER(product_name),LOWER(product_description),product_price,product_tariff) LIKE ? ESCAPE '!'",
                    ['%'.LegacyLike::escape(mb_strtolower($keyword)).'%'],
                );
            }
        }

        return view('products.partial_products_table', ['rows' => $products->get()]);
    }

    /** Ajax::filter_invoices(): every keyword (lower-cased) has to appear in the number, dates, client or amounts. */
    public function filterInvoices(Request $request): View
    {
        $invoices = Invoice::query()->listing();

        foreach (explode(' ', (string) $request->post('filter_query')) as $keyword) {
            if ($keyword) {
                $invoices->whereRaw(
                    "CONCAT_WS('^',LOWER(invoice_number),invoice_date_created,invoice_date_due,LOWER(client_title),LOWER(client_name),LOWER(client_surname),invoice_total,invoice_balance) LIKE ? ESCAPE '!'",
                    ['%'.LegacyLike::escape(mb_strtolower($keyword)).'%'],
                );
            }
        }

        return view('invoices.partial_invoice_table', ['invoices' => $invoices->get(), 'invoiceStatuses' => Invoice::statuses()]);
    }

    /** Ajax::filter_payments(): every keyword (lower-cased) has to appear in the date, invoice number, client, amount, method or note. */
    public function filterPayments(Request $request): View
    {
        $payments = Payment::query()->listing();

        foreach (explode(' ', (string) $request->post('filter_query')) as $keyword) {
            if ($keyword) {
                $payments->whereRaw(
                    "CONCAT_WS('^',payment_date,LOWER(invoice_number),LOWER(client_title),LOWER(client_name),LOWER(client_surname),payment_amount,LOWER(payment_method_name),LOWER(payment_note)) LIKE ? ESCAPE '!'",
                    ['%'.LegacyLike::escape(mb_strtolower($keyword)).'%'],
                );
            }
        }

        return view('payments.partial_payment_table', ['payments' => $payments->get()]);
    }
}
