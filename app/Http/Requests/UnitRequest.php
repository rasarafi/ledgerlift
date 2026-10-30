<?php

namespace App\Http\Requests;

/** Mdl_units::validation_rules() */
class UnitRequest extends LegacyFormRequest
{
    public function rules(): array
    {
        return ['unit_name' => 'required', 'unit_name_plrl' => 'required'];
    }

    public function attributes(): array
    {
        return ['unit_name' => __('ip.unit_name'), 'unit_name_plrl' => __('ip.unit_name_plrl')];
    }
}
