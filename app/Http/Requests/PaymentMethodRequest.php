<?php

namespace App\Http\Requests;

/** Mdl_payment_methods::validation_rules() */
class PaymentMethodRequest extends LegacyFormRequest
{
    public function rules(): array
    {
        return ['payment_method_name' => 'required'];
    }

    public function attributes(): array
    {
        return ['payment_method_name' => __('ip.payment_method')];
    }
}
