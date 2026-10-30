<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CI form_validation's `numeric` rule: `^[\-+]?[0-9]*\.?[0-9]+$` (so "1e3" is not numeric and
 * "5\n" is, `$` matching before a final newline). Implicit: CI runs it on a whitespace-only
 * value, which Laravel skips for ordinary rules; only an absent or empty field is exempt.
 */
class LegacyNumeric implements ValidationRule
{
    public bool $implicit = true;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || ! preg_match('/^[\-+]?[0-9]*\.?[0-9]+$/', $value)) {
            $fail('The :attribute field must contain only numbers.');
        }
    }
}
