<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceRequest;
use App\Http\Requests\InvoiceSaveRequest;
use App\Http\Requests\InvoiceTaxRateRequest;
use App\Http\Responses\LegacyJson;
use App\Models\Client;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * modules/invoices/controllers/Ajax.php. The flash messages are those Response_Model::save()
 * and delete() leave behind: of several in one request, the last one written.
 */
class InvoiceAjaxController extends Controller
{
    /** Ajax::modal_create_invoice(): the groups, and the client posted by a client's "create invoice" button. */
    public function modalCreateInvoice(Request $request): View
    {
        return view('invoices.modal_create_invoice', [
            'invoiceGroups' => DB::table('ip_invoice_groups')->orderBy('invoice_group_name')->get(),
            'client' => Client::query()->listing()->where('ip_clients.client_id', $request->post('client_id'))->first(),
        ]);
    }

    /** Ajax::create() */
    public function create(Request $request, InvoiceRequest $invoiceRequest, InvoiceService $invoices): Response
    {
        $validator = $invoiceRequest->legacyValidator();

        if ($validator->fails()) {
            return LegacyJson::make(['success' => 0, 'validation_errors' => $invoiceRequest->jsonErrors($validator)]);
        }

        $invoiceId = $invoices->create($invoiceRequest->persisted(), (int) $request->user()?->getAuthIdentifier());
        $request->session()->flash('alert_success', __('ip.record_successfully_created'));

        return LegacyJson::make(['success' => 1, 'invoice_id' => $invoiceId]);
    }

    /** Ajax::save(): the invoice's own save comes last and flashes the update. */
    public function save(Request $request, InvoiceSaveRequest $saveRequest, InvoiceService $invoices): Response
    {
        $validator = $saveRequest->legacyValidator();

        if ($validator->fails()) {
            return LegacyJson::make(['success' => 0, 'validation_errors' => $saveRequest->jsonErrors($validator)]);
        }

        $documents = $invoices->save((int) $request->post('invoice_id'), $request->post());
        $request->session()->flash('alert_success', __('ip.record_successfully_updated'));

        return LegacyJson::text(implode('', array_map(fn (array $document) => (string) json_encode($document), $documents)));
    }

    /** Ajax::save_invoice_tax_rate(): a failure answers validation_errors() as one string. */
    public function saveInvoiceTaxRate(Request $request, InvoiceTaxRateRequest $taxRateRequest, InvoiceService $invoices): Response
    {
        $validator = $taxRateRequest->legacyValidator();

        if ($validator->fails()) {
            return LegacyJson::make(['success' => 0, 'validation_errors' => $taxRateRequest->errorString($validator)]);
        }

        $response = $invoices->addTaxRate($taxRateRequest->persisted());
        $request->session()->flash('alert_success', __('ip.record_successfully_created'));

        return LegacyJson::make($response);
    }

    /** Ajax::delete_item() */
    public function deleteItem(Request $request, InvoiceService $invoices, string $invoiceId): Response
    {
        $deleted = $invoices->deleteItem((int) $invoiceId, (int) $request->post('item_id'));

        if ($deleted) {
            $request->session()->flash('alert_success', __('ip.record_successfully_deleted'));
        }

        return LegacyJson::make(['success' => (int) $deleted]);
    }
}
