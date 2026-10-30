<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Mdl_clients::validate_einvoicing_version(): an empty version is allowed, any other has to be
 * the id of an XML configuration (is_valid_xml_config_id()). E-invoicing is not ported, so
 * there is none to choose, as on a legacy install without configurations.
 */
class LegacyEinvoicingVersion implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! empty($value)) {
            $fail(__('ip.einvoicing_version_invalid'));
        }
    }
}
