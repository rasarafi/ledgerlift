<?php

namespace App\Http\Requests;

/**
 * Mdl_invoices::validation_rules_save_invoice(), the rules of invoices/ajax/save: the number is
 * unique among the other invoices (MY_Form_validation::is_unique() with the id of
 * set_id()), and only when it is not empty. `invoice_time_created` and `invoice_password` have
 * no rules CI runs.
 */
class InvoiceSaveRequest extends LegacyFormRequest
{
    public function rules(): array
    {
        $id = $this->post('invoice_id');

        return [
            // MySQL compares the id as a number, as (int) reads it.
            'invoice_number' => 'unique:ip_invoices,invoice_number'.(is_scalar($id) && $id ? ','.(int) $id.',invoice_id' : ''),
            'invoice_date_created' => 'required',
            'invoice_date_due' => 'required',
        ];
    }

    public function attributes(): array
    {
        return [
            'invoice_number' => __('ip.invoice').' #',
            'invoice_date_created' => __('ip.date'),
            'invoice_date_due' => __('ip.due_date'),
        ];
    }

    public function messages(): array
    {
        return ['unique' => 'The :attribute field must contain a unique value.'];
    }
}
