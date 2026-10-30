<?php

namespace App\Http\Requests;

/** Mdl_families::validation_rules() */
class FamilyRequest extends LegacyFormRequest
{
    public function rules(): array
    {
        return ['family_name' => 'required'];
    }

    public function attributes(): array
    {
        return ['family_name' => __('ip.family_name')];
    }
}
