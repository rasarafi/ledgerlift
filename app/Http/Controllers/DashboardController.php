<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceAmount;
use Illuminate\Support\Facades\Lang;
use Illuminate\View\View;

/** modules/dashboard/controllers/Dashboard.php; the quote, project and task panels are not ported. */
class DashboardController extends Controller
{
    public function index(): View
    {
        $period = (string) get_setting('invoice_overview_period');
        $overdue = Invoice::query()->listing()->havingRaw('is_overdue = 1')->get();

        return view('dashboard.index', [
            'invoiceStatusTotals' => InvoiceAmount::statusTotals($period),
            // lang(str_replace('-', '_', $period)): a key that does not exist prints nothing.
            'invoiceStatusPeriod' => Lang::has('ip.'.str_replace('-', '_', $period)) ? __('ip.'.str_replace('-', '_', $period)) : '',
            // Mdl_invoices->limit(10)->get(): the default order, newest first.
            'invoices' => Invoice::query()->listing()->addSelect('ip_invoice_sumex.sumex_id')->limit(10)->get(),
            'invoiceStatuses' => Invoice::statuses(),
            'overdueTotal' => $overdue->sum(fn (Invoice $invoice) => (float) $invoice->invoice_balance),
            'hasOverdue' => $overdue->isNotEmpty(),
        ]);
    }
}
