<?php

namespace App\Http\Requests;

use Illuminate\Support\Arr;

/** Mdl_client_notes::validation_rules() and db_array() */
class ClientNoteRequest extends LegacyFormRequest
{
    public function rules(): array
    {
        return ['client_id' => 'required', 'client_note' => 'required'];
    }

    public function attributes(): array
    {
        return ['client_id' => __('ip.client'), 'client_note' => __('ip.note')];
    }

    /**
     * @return array<string, mixed>
     */
    public function persisted(): array
    {
        return Arr::only($this->post(), array_keys($this->rules())) + ['client_note_date' => date('Y-m-d')];
    }
}
