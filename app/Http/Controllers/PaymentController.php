<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Http\Responses\LegacyRedirect;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\LegacyPager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** modules/payments/controllers/Payments.php (without the online logs and custom fields, which are not ported) */
class PaymentController extends Controller
{
    /** Payments::index(): Mdl_payments::paginate() with `default_list_limit`. */
    public function index(string $offset = '0'): View
    {
        $query = Payment::query()->listing();
        $pager = new LegacyPager((int) $offset, LegacyPager::defaultPerPage(), (clone $query)->toBase()->getCountForPagination());

        return view('payments.index', [
            'payments' => $query->offset($pager->offset)->limit($pager->perPage)->get(),
            'pager' => $pager,
            'filterDisplay' => true,
            'filterPlaceholder' => __('ip.filter_payments'),
            'filterMethod' => 'filter_payments',
        ]);
    }

    /** Payments::form() (Payments.php:50-149) */
    public function form(Request $request, PaymentRequest $paymentRequest, PaymentService $payments, ?string $id = null): View|RedirectResponse
    {
        if ($request->post('btn_cancel')) {
            return LegacyRedirect::to($request, url('payments'));
        }

        $form = $request->post();
        $errors = null;

        // CI form_validation only has rules to run on a POST.
        if ($request->isMethod('POST')) {
            $validator = $paymentRequest->legacyValidator();

            if (! $validator->fails()) {
                // Response_Model::save() flashes by whether it got an id (PHP truthiness: '0' is none).
                $id ? $payments->update((int) $id, $paymentRequest->persisted()) : $payments->add($paymentRequest->persisted());

                return LegacyRedirect::to($request, url('payments'))
                    ->with('alert_success', __($id ? 'ip.record_successfully_updated' : 'ip.record_successfully_created'));
            }

            $errors = $validator->errors();
        }

        $payment = $id ? Payment::query()->listing()->where('ip_payments.payment_id', $id)->first() : null;

        if (! $request->post('btn_submit')) {
            // MY_Model::prep_form(): the record is loaded only when nothing was posted; an id with a post is a 404.
            if ($id && ($form !== [] || $payment === null)) {
                abort(404);
            }

            $form = $payment?->getAttributes() ?? ['payment_date' => date('Y-m-d')];
        }

        $openInvoices = Invoice::query()->listing()
            ->whereIn('ip_invoices.invoice_status_id', [2, 3])
            ->where('ip_invoice_amounts.invoice_balance', '<>', '0.00')
            ->get();

        // Payments.php:124-129
        $amounts = [];
        $invoicePaymentMethods = [];

        foreach ($openInvoices as $openInvoice) {
            $amounts['invoice'.$openInvoice->invoice_id] = format_amount($openInvoice->invoice_balance);
            $invoicePaymentMethods['invoice'.$openInvoice->invoice_id] = (string) $openInvoice->payment_method;
        }

        return view('payments.form', [
            'form' => $form,
            'paymentId' => $id ? (int) $id : null,
            'payment' => $payment,
            'paymentMethods' => DB::table('ip_payment_methods')->get(),
            'openInvoices' => $openInvoices,
            // Embedded in a single-quoted script string: json_encode() with the quote and markup escapes on.
            'amounts' => json_encode($amounts, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP),
            'invoicePaymentMethods' => json_encode($invoicePaymentMethods, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP),
        ])->withErrors($errors ?? []);
    }

    /** Payments::delete(): Response_Model::delete() flashes the deletion. */
    public function delete(Request $request, PaymentService $payments, string $id): RedirectResponse
    {
        $payments->delete((int) $id);

        return LegacyRedirect::to($request, url('payments'))->with('alert_success', __('ip.record_successfully_deleted'));
    }
}
