<?php

namespace App\Http\Requests;

use Illuminate\Support\Arr;

/** Mdl_invoice_tax_rates::validation_rules() and db_array() */
class InvoiceTaxRateRequest extends LegacyFormRequest
{
    public function rules(): array
    {
        return ['invoice_id' => 'required', 'tax_rate_id' => 'required', 'include_item_tax' => 'required'];
    }

    public function attributes(): array
    {
        return [
            'invoice_id' => __('ip.invoice'),
            'tax_rate_id' => __('ip.tax_rate'),
            'include_item_tax' => __('ip.tax_rate_placement'),
        ];
    }

    /** @return array<string, mixed> */
    public function persisted(): array
    {
        return Arr::only($this->post(), array_keys($this->rules()));
    }
}
