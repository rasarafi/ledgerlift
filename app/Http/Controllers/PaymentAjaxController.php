<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Http\Responses\LegacyJson;
use App\Services\PaymentService;
use App\Support\SafeReferer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** modules/payments/controllers/Ajax.php */
class PaymentAjaxController extends Controller
{
    /** Ajax::add(): Response_Model::save() flashes the creation. */
    public function add(Request $request, PaymentRequest $paymentRequest, PaymentService $payments): Response
    {
        $validator = $paymentRequest->legacyValidator();

        if ($validator->fails()) {
            return LegacyJson::make(['success' => 0, 'validation_errors' => $paymentRequest->jsonErrors($validator)]);
        }

        $response = $payments->add($paymentRequest->persisted());
        $request->session()->flash('alert_success', __('ip.record_successfully_created'));

        return LegacyJson::make($response);
    }

    /** Ajax::modal_add_payment(): what the "Enter payment" links post, and where a saved payment sends the page back to. */
    public function modalAddPayment(Request $request): View
    {
        return view('payments.modal_add_payment', [
            'paymentMethods' => DB::table('ip_payment_methods')->get(),
            'invoiceId' => $request->post('invoice_id'),
            'invoiceBalance' => $request->post('invoice_balance'),
            'invoicePaymentMethod' => $request->post('invoice_payment_method'),
            'paymentCfExist' => $request->post('payment_cf_exist'),
            'returnTo' => SafeReferer::forJavascript(SafeReferer::url($request->headers->get('referer'), url('invoices'))),
        ]);
    }
}
