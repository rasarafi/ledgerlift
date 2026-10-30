<?php

namespace App\Http\Requests;

use App\Rules\PaymentAmount;
use Illuminate\Support\Arr;

/** Mdl_payments::validation_rules() (Mdl_payments.php:302-329) */
class PaymentRequest extends LegacyFormRequest
{
    public function rules(): array
    {
        return [
            'invoice_id' => 'required',
            'payment_date' => 'required',
            'payment_amount' => [new PaymentAmount($this->post('invoice_id'), $this->post('payment_id')), 'required'],
            'payment_method_id' => '',
            'payment_note' => '',
        ];
    }

    public function attributes(): array
    {
        return [
            'invoice_id' => __('ip.invoice'),
            'payment_date' => __('ip.date'),
            'payment_amount' => __('ip.payment'),
            'payment_method_id' => __('ip.payment_method'),
            'payment_note' => __('ip.note'),
        ];
    }

    /**
     * MY_Model::db_array(): only the posted keys that appear in validation_rules(). CI stores an array as
     * the text "Array".
     *
     * @return array<string, mixed>
     */
    public function persisted(): array
    {
        return array_map(fn (mixed $value) => is_array($value) ? 'Array' : $value, Arr::only($this->post(), array_keys($this->rules())));
    }
}
