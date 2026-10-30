<?php

namespace App\Http\Requests;

/** Mdl_invoice_groups::validation_rules() */
class InvoiceGroupRequest extends LegacyFormRequest
{
    public function rules(): array
    {
        return [
            'invoice_group_name' => 'required',
            'invoice_group_identifier_format' => 'required',
            'invoice_group_next_id' => 'required',
            'invoice_group_left_pad' => 'required',
        ];
    }

    public function attributes(): array
    {
        return [
            'invoice_group_name' => __('ip.name'),
            'invoice_group_identifier_format' => __('ip.identifier_format'),
            'invoice_group_next_id' => __('ip.next_id'),
            'invoice_group_left_pad' => __('ip.left_pad'),
        ];
    }
}
