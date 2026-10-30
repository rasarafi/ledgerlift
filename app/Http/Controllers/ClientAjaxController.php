<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientNoteRequest;
use App\Http\Responses\LegacyJson;
use App\Models\Client;
use App\Models\ClientNote;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** modules/clients/controllers/Ajax.php */
class ClientAjaxController extends Controller
{
    /**
     * Ajax::name_query(): the active clients whose name, surname or full name starts with (or,
     * permissive, contains) the query. An empty query answers `[]` and then searches anyway, as
     * the early json_encode_ajax() has no return: the body holds both documents.
     */
    public function nameQuery(Request $request): Response
    {
        $query = $request->query('query');

        if (! is_string($query) && $query !== null) {
            return LegacyJson::make([]);
        }

        $body = empty($query) ? '[]' : '';

        // LIKE wildcards are stripped from the input, not escaped.
        $pattern = ($request->query('permissive_search_clients') ? '%' : '').str_replace(['%', '_'], '', (string) $query).'%';

        // The controller's order_by() comes before the model's default one.
        $clients = Client::query()->orderBy('client_name')->listing()
            ->where('client_active', 1)
            ->havingRaw('(client_name LIKE ? OR client_surname LIKE ? OR client_fullname LIKE ?)', [$pattern, $pattern, $pattern])
            ->get();

        return LegacyJson::text($body.json_encode($clients->map(fn (Client $client) => [
            'id' => (string) $client->client_id,
            'text' => htmlsc(format_client($client, false)),
        ])->all()));
    }

    /**
     * Ajax::save_preference_permissive_search_clients(): only 0 and 1 are saved (`$` also lets a
     * trailing newline through), anything else ends the request. Mdl_settings::save() updates the
     * setting when its stored value is not NULL and inserts it otherwise.
     */
    public function savePreferencePermissiveSearchClients(Request $request): Response
    {
        $value = $request->query('permissive_search_clients');

        // preg_match() takes a string only; legacy fails with a TypeError (HTTP 500) otherwise.
        abort_if(is_array($value), 500);

        if (is_string($value) && preg_match('!^[0-1]{1}$!', $value)) {
            $key = 'enable_permissive_search_clients';

            if (DB::table('ip_settings')->where('setting_key', $key)->value('setting_value') !== null) {
                DB::table('ip_settings')->where('setting_key', $key)->update(['setting_value' => $value]);
            } else {
                DB::table('ip_settings')->insert(['setting_key' => $key, 'setting_value' => $value]);
            }
        }

        return new Response('', 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** Ajax::save_client_note(): Response_Model::save() flashes its success even here. */
    public function saveClientNote(Request $request, ClientNoteRequest $noteRequest): Response
    {
        $validator = $noteRequest->legacyValidator();

        if (! $validator->fails()) {
            ClientNote::query()->insert($noteRequest->persisted());
            $request->session()->flash('alert_success', __('ip.record_successfully_created'));

            return LegacyJson::make(['success' => 1, 'new_token' => csrf_token()]);
        }

        // json_errors(): the errors of the posted fields, in the order they were posted, in CI's <p> delimiters.
        $errors = [];

        foreach (array_keys($request->post()) as $field) {
            if ($validator->errors()->has($field)) {
                $errors[$field] = '<p>'.$validator->errors()->first($field).'</p>';
            }
        }

        return LegacyJson::make(['success' => 0, 'new_token' => csrf_token(), 'validation_errors' => $errors]);
    }

    /** Ajax::delete_client_note(): an unknown note is "not deleted", an empty id counts as deleted. */
    public function deleteClientNote(Request $request): Response
    {
        $id = $request->post('client_note_id');
        $success = 0;

        if (ClientNote::query()->where('client_note_id', $id)->exists() || empty($id)) {
            ClientNote::query()->where('client_note_id', $id)->delete();
            $request->session()->flash('alert_success', __('ip.record_successfully_deleted'));
            $success = 1;
        }

        return LegacyJson::make(['success' => $success]);
    }

    /** Ajax::load_client_notes(): the notes of the posted client, as HTML. */
    public function loadClientNotes(Request $request): View
    {
        return view('clients.partial_notes', [
            'clientNotes' => ClientNote::query()->where('client_id', $request->post('client_id'))->orderByDesc('client_note_date')->get(),
        ]);
    }
}
