<?php

namespace App\Rules;

use App\Services\PaymentService;
use App\Support\NumberFormat;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Mdl_payments::validate_payment_amount() (Mdl_payments.php:90-117): the amount may not exceed the
 * invoice's balance, to which the edited payment's own amount is added back. There is no lower bound.
 * CI runs a callback rule before `required` and on an empty amount too (Form_validation::_prepare_rules()),
 * so this rule is implicit and goes first.
 */
class PaymentAmount implements ValidationRule
{
    public bool $implicit = true;

    public function __construct(private readonly mixed $invoiceId, private readonly mixed $paymentId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $balance = app(PaymentService::class)->payableBalance($this->invoiceId, $this->paymentId);

        if ($balance === null) {
            // The callback returns false without set_message(): CI words the failure itself (Form_validation::_get_error_message()).
            $fail('Unable to access an error message corresponding to your field name :attribute.(validate_payment_amount)');

            return;
        }

        if ((float) app(NumberFormat::class)->standardize(is_scalar($value) ? (string) $value : null) > $balance) {
            $fail(__('ip.payment_cannot_exceed_balance'));
        }
    }
}
