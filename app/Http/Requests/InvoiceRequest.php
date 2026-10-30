<?php

namespace App\Http\Requests;

use Illuminate\Support\Arr;

/**
 * Mdl_invoices::validation_rules(), the rules of invoices/ajax/create. `invoice_time_created`
 * has no `field` key and `user_id` a `rule` typo, so CI validates neither (set_rules() skips
 * them) but db_array() keeps both, as it keeps the password and the payment method.
 */
class InvoiceRequest extends LegacyFormRequest
{
    public function rules(): array
    {
        return [
            'client_id' => 'required',
            'invoice_date_created' => 'required',
            'invoice_time_created' => '',
            'invoice_group_id' => 'required',
            'invoice_password' => '',
            'user_id' => '',
            'payment_method' => '',
        ];
    }

    public function attributes(): array
    {
        return [
            'client_id' => __('ip.client'),
            'invoice_date_created' => __('ip.invoice_date'),
            'invoice_group_id' => __('ip.invoice_group'),
        ];
    }

    /**
     * MY_Model::db_array(): the posted fields the rules name, in the order they were posted.
     *
     * @return array<string, mixed>
     */
    public function persisted(): array
    {
        return Arr::only($this->post(), array_keys($this->rules()));
    }
}
