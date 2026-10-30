<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Http\Responses\LegacyRedirect;
use App\Models\Client;
use App\Models\ClientNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\OrphanCleaner;
use App\Services\UserClients;
use App\Support\LegacyPager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** modules/clients/controllers/Clients.php */
class ClientController extends Controller
{
    /** Clients::view(): the stored pages of the tabs live this long (CI's mark_as_temp() default). */
    private const TAB_PAGES_SECONDS = 300;

    public function index(Request $request): RedirectResponse
    {
        return LegacyRedirect::to($request, url('clients/status/active'));
    }

    /** Clients::status(): `active` and `inactive` filter, any other status lists every client. */
    public function status(string $status = '', string $offset = '0'): View
    {
        $query = Client::query()->listing();

        // The method's default is `active` when the URL has no status segment.
        $filter = $status === '' ? 'active' : $status;

        if (in_array($filter, ['active', 'inactive'], true)) {
            $query->where('client_active', $filter === 'active' ? 1 : 0);
        }

        $query->withInvoiceTotals();

        $pager = $this->pager($query, (int) $offset, LegacyPager::defaultPerPage());

        return view('clients.index', [
            'rows' => $query->offset($pager->offset)->limit($pager->perPage)->get(),
            'pager' => $pager,
            // The pager and the buttons are built from the third URI segment, as typed ('' when absent).
            'statusSegment' => $status,
            'filterDisplay' => true,
            'filterPlaceholder' => __('ip.filter_clients'),
            'filterMethod' => 'filter_clients',
        ]);
    }

    /** Clients::form() */
    public function form(Request $request, ClientRequest $clientRequest, UserClients $userClients, ?string $id = null): View|RedirectResponse
    {
        if ($request->post('btn_cancel')) {
            return LegacyRedirect::to($request, url('clients'));
        }

        // Loose comparisons as in PHP: an absent is_update counts as 0.
        $newClient = false;

        if ($request->post('is_update') == 0 && $request->post('client_name') != '') {
            $surname = $request->post('client_surname');
            $exists = Client::query()->where('client_name', $request->post('client_name'))->where('client_surname', $surname)->exists();

            if ($exists) {
                return LegacyRedirect::to($request, url('clients/form'))->with('alert_error', __('ip.client_already_exists'));
            }

            $newClient = true;
        }

        $form = $request->post();
        $errors = null;

        // CI form_validation only has rules to run on a POST (a bare token post included).
        if ($request->isMethod('POST')) {
            $validator = $clientRequest->legacyValidator();

            if (! $validator->fails()) {
                $message = $id ? 'ip.record_successfully_updated' : 'ip.record_successfully_created';
                $id = $this->save($clientRequest, $id);

                if ($newClient) {
                    $userClients->assignAllClients();
                }

                return LegacyRedirect::to($request, url('clients/view/'.$id))
                    ->with('alert_success', __($message));
            }

            $errors = $validator->errors();
        }

        if ($id && ! $request->post('btn_submit')) {
            // MY_Model::prep_form() only loads the record when nothing was posted.
            $row = ($form === [] ? Client::query()->where('client_id', $id)->first() : null) ?? abort(404);
            $form = $row->getAttributes() + ['is_update' => true];
        }

        return view('clients.form', [
            'form' => $form,
            'clientId' => $id,
            'selectedCountry' => ($form['client_country'] ?? '') ?: get_setting('default_country'),
        ])->withErrors($errors ?? []);
    }

    /** Clients::view() */
    public function view(Request $request, string $id, string $tab = 'detail', string $page = '0'): View
    {
        $client = Client::query()->listing()->withInvoiceTotals(total: true, paid: true)
            ->where('ip_clients.client_id', $id)->first() ?? abort(404);

        $pages = $this->tabPages($request, $tab, $page);
        $perPage = LegacyPager::defaultPerPage();

        $invoices = Invoice::query()->listing()->where('ip_invoices.client_id', $id);
        $payments = Payment::query()->listing()->where('ip_clients.client_id', $id);
        $invoicePager = $this->pager($invoices, (int) $pages['invoices'], $perPage);
        $paymentPager = $this->pager($payments, (int) $pages['payments'], $perPage);

        return view('clients.view', [
            'client' => $client,
            'clientNotes' => ClientNote::query()->where('client_id', $id)->orderByDesc('client_note_date')->get(),
            'invoices' => $invoices->offset($invoicePager->offset)->limit($perPage)->get(),
            'invoicePager' => $invoicePager,
            'invoiceStatuses' => Invoice::statuses(),
            'payments' => $payments->offset($paymentPager->offset)->limit($perPage)->get(),
            'paymentPager' => $paymentPager,
            'activeTab' => $tab,
        ]);
    }

    /** Clients::delete(): the model's delete (Response_Model flash included) and delete_orphans(). */
    public function delete(Request $request, OrphanCleaner $orphans, string $id): RedirectResponse
    {
        Client::query()->where('client_id', $id)->delete();
        $orphans->run();

        return LegacyRedirect::to($request, url('clients'))->with('alert_success', __('ip.record_successfully_deleted'));
    }

    /**
     * MY_Model::save() with Mdl_clients::db_array(): both dates on create, the modified date on
     * update; an unchecked `client_active` is 0.
     */
    private function save(ClientRequest $request, ?string $id): string
    {
        $data = $request->persisted();

        // Clients::form(): the "custom" title choice is replaced by the text typed next to it.
        if (($data['client_title'] ?? null) == 'custom') {
            $data['client_title'] = $request->post('client_title_custom');
        }

        $data['client_active'] ??= 0;
        $now = date('Y-m-d H:i:s');

        if ($id) {
            Client::query()->where('client_id', $id)->update($data + ['client_date_modified' => $now]);

            return $id;
        }

        return (string) Client::query()->insertGetId($data + ['client_date_created' => $now, 'client_date_modified' => $now]);
    }

    /**
     * Clients::view(): the page each tab was left on is kept in the session for 300 seconds, so
     * paging one tab does not reset another. Opening the details tab forgets them.
     *
     * @return array<string, mixed>
     */
    private function tabPages(Request $request, string $tab, string $page): array
    {
        $pages = ['invoices' => 0, 'quotes' => 0, 'payments' => 0];
        $stored = $request->session()->get('clientview');

        if ($tab == 'detail') {
            $request->session()->forget('clientview');

            return $pages;
        }

        if (is_array($stored) && $stored['expires'] > time()) {
            $pages = $stored['pages'];
        }

        $pages[$tab] = $page;
        $request->session()->put('clientview', ['pages' => $pages, 'expires' => time() + self::TAB_PAGES_SECONDS]);

        return $pages;
    }

    /**
     * @param  Builder<*>  $query
     */
    private function pager(Builder $query, int $offset, int $perPage): LegacyPager
    {
        return new LegacyPager($offset, $perPage, (clone $query)->toBase()->getCountForPagination());
    }
}
