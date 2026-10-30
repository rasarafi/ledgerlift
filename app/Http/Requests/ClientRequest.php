<?php

namespace App\Http\Requests;

use App\Rules\LegacyEinvoicingVersion;
use App\Rules\LegacyEmail;
use App\Support\DateFormat;
use Illuminate\Support\Arr;

/**
 * Mdl_clients::validation_rules(). E-invoicing is not ported: its two fields are kept, as legacy
 * stores them, but no XML configuration exists to choose a version from, so a version is
 * invalid unless empty (validate_einvoicing_version() on an install with none).
 */
class ClientRequest extends LegacyFormRequest
{
    public function rules(): array
    {
        return [
            'client_title' => '',
            'client_name' => 'required',
            'client_surname' => '',
            'client_active' => '',
            'client_language' => '',
            'client_address_1' => '',
            'client_address_2' => '',
            'client_city' => '',
            'client_state' => '',
            'client_zip' => '',
            'client_country' => '',
            'client_phone' => '',
            'client_fax' => '',
            'client_mobile' => '',
            'client_email' => [new LegacyEmail],
            'client_web' => '',
            'client_company' => '',
            'client_vat_id' => '',
            'client_tax_code' => '',
            'client_invoicing_contact' => '',
            'client_einvoicing_version' => [new LegacyEinvoicingVersion],
            'client_einvoicing_active' => '',
            'client_birthdate' => '',
            'client_gender' => '',
            'client_avs' => '',
            'client_insurednumber' => '',
            'client_veka' => '',
        ];
    }

    public function attributes(): array
    {
        return [
            'client_title' => __('ip.client_title'),
            'client_name' => __('ip.client_name'),
            'client_surname' => __('ip.client_surname'),
            'client_language' => __('ip.language'),
            'client_email' => __('ip.email_address'),
            'client_avs' => __('ip.sumex_ssn'),
            'client_insurednumber' => __('ip.sumex_insurednumber'),
            'client_veka' => __('ip.sumex_veka'),
        ];
    }

    /**
     * The values MY_Model::db_array() stores. CI's form_validation writes what its rules
     * return back into $_POST: `trim` on the language, country, e-mail and invoicing contact,
     * and the callbacks convert_date() (birthdate, display format to Y-m-d) and fix_avs()
     * (Mdl_clients.php:131-155), which run on a missing field too and leave it missing.
     *
     * @return array<string, mixed>
     */
    public function persisted(): array
    {
        $post = Arr::only($this->post(), array_keys($this->rules()));

        foreach (['client_language', 'client_country', 'client_email', 'client_invoicing_contact'] as $field) {
            if (isset($post[$field]) && is_string($post[$field])) {
                $post[$field] = trim($post[$field]);
            }
        }

        if (isset($post['client_birthdate']) && is_string($post['client_birthdate'])) {
            $post['client_birthdate'] = $post['client_birthdate'] === '' ? '' : app(DateFormat::class)->toMysql($post['client_birthdate']);
        }

        if (isset($post['client_avs']) && is_string($post['client_avs'])) {
            $post['client_avs'] = $this->fixAvs($post['client_avs']);
        }

        return $post;
    }

    /** Mdl_clients::fix_avs(): 756.1234.5678.97 or 13 digits are kept without dots, anything else is cleared. */
    private function fixAvs(string $input): string
    {
        if ($input != '') {
            if (preg_match('/(\d{3})\.(\d{4})\.(\d{4})\.(\d{2})/', $input, $matches)) {
                return $matches[1].$matches[2].$matches[3].$matches[4];
            }

            if (preg_match('/^\d{13}$/', $input)) {
                return $input;
            }
        }

        return '';
    }
}
