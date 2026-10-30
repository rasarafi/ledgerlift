<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CI form_validation's `trim|valid_email` (system/libraries/Form_validation.php:1239): the
 * trimmed value must pass FILTER_VALIDATE_EMAIL, its domain converted from IDN first. Each rule
 * sees the value the one before it left, and an empty value skips valid_email, so a field of
 * spaces only is valid.
 */
class LegacyEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = trim((string) $value);

        if ($email === '') {
            return;
        }

        if (function_exists('idn_to_ascii') && preg_match('#\A([^@]+)@(.+)\z#', $email, $matches)) {
            $domain = idn_to_ascii($matches[2], 0, INTL_IDNA_VARIANT_UTS46);

            if ($domain !== false) {
                $email = $matches[1].'@'.$domain;
            }
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fail('The :attribute field must contain a valid email address.');
        }
    }
}
