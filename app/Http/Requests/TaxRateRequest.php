<?php

namespace App\Http\Requests;

/** Mdl_tax_rates::validation_rules() */
class TaxRateRequest extends LegacyFormRequest
{
    public function rules(): array
    {
        return ['tax_rate_name' => 'required', 'tax_rate_percent' => 'required'];
    }

    public function attributes(): array
    {
        return ['tax_rate_name' => __('ip.tax_rate_name'), 'tax_rate_percent' => __('ip.tax_rate_percent')];
    }
}
