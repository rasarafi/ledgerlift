<?php

namespace App\Services;

use App\Support\DateFormat;
use App\Support\NumberFormat;
use Illuminate\Support\Facades\DB;

/**
 * What the payments module writes, in legacy's order: Mdl_payments::save() and delete()
 * (Mdl_payments.php:122-156, 171-201). Validation and the flash messages are the controllers'.
 */
final class PaymentService
{
    public function __construct(
        private readonly InvoiceCalculator $calculator,
        private readonly NumberFormat $numberFormat,
        private readonly DateFormat $dates,
        private readonly OrphanCleaner $orphans,
    ) {}

    /**
     * validate_payment_amount() (Mdl_payments.php:90-117): what a payment on the invoice may be at most.
     * The balance, plus the payment's own amount when it is being edited. Null when the invoice has no
     * amounts row, where legacy fails without a message.
     */
    public function payableBalance(mixed $invoiceId, mixed $paymentId): ?float
    {
        $amounts = is_scalar($invoiceId) ? DB::table('ip_invoice_amounts')->where('invoice_id', $invoiceId)->first(['invoice_balance']) : null;

        if ($amounts === null) {
            return null;
        }

        $balance = (float) $amounts->invoice_balance;

        // :104 a truthy id (PHP: not '' and not '0'); a payment that does not exist adds nothing.
        if ($paymentId && is_scalar($paymentId)) {
            $balance += (float) DB::table('ip_payments')->where('payment_id', $paymentId)->value('payment_amount');
        }

        return $balance;
    }

    /**
     * Mdl_payments::save() for a new payment, once validation has passed. There is no `amount > 0` check:
     * a zero or negative payment is saved like any other.
     *
     * @param  array<string, mixed>  $input  The posted fields that validation_rules() names.
     * @return array{success: int, payment_id: int}
     */
    public function add(array $input): array
    {
        return ['success' => 1, 'payment_id' => $this->save(null, $input)];
    }

    /**
     * Mdl_payments::save($id): an update of a payment that does not exist changes nothing, and the posted
     * invoice is recalculated all the same.
     *
     * @param  array<string, mixed>  $input
     * @return array{success: int, payment_id: int}
     */
    public function update(int $paymentId, array $input): array
    {
        return ['success' => 1, 'payment_id' => $this->save($paymentId, $input)];
    }

    /**
     * Mdl_payments::delete() (:171-201): the payment goes, the invoice is recalculated, a paid invoice goes
     * back to sent (is_read_only stays), then the orphans are swept.
     */
    public function delete(int $paymentId): void
    {
        $invoiceId = DB::table('ip_payments')->where('payment_id', $paymentId)->value('invoice_id');

        if ($invoiceId === null) {
            return;
        }

        DB::table('ip_payments')->where('payment_id', $paymentId)->delete();
        $this->calculator->recalculateInvoice((int) $invoiceId);

        DB::table('ip_invoices')->where('invoice_id', $invoiceId)->where('invoice_status_id', 4)->update(['invoice_status_id' => 2]);

        $this->orphans->run();
    }

    /**
     * Mdl_payments::save() (:122-156) with db_array() (:161-169): the date and the amount are converted,
     * the row is written, the invoice recalculated, a settled invoice (paid >= total) set to paid, and the
     * invoice recalculated again, which is what makes it read-only.
     *
     * @param  array<string, mixed>  $input
     */
    private function save(?int $paymentId, array $input): int
    {
        $data = $input;
        $data['payment_date'] = $this->dates->toMysql((string) ($data['payment_date'] ?? ''));
        $amount = $data['payment_amount'] ?? null;
        $data['payment_amount'] = $this->numberFormat->standardize(is_string($amount) || is_int($amount) || is_float($amount) ? $amount : null);

        if ($paymentId) {
            DB::table('ip_payments')->where('payment_id', $paymentId)->update($data);
        } else {
            $paymentId = (int) DB::table('ip_payments')->insertGetId($data);
        }

        $invoiceId = (int) ($data['invoice_id'] ?? 0);
        $this->calculator->recalculateInvoice($invoiceId);

        $amounts = DB::table('ip_invoice_amounts')->where('invoice_id', $invoiceId)->first(['invoice_paid', 'invoice_total']);

        // :137-139 no amounts row: legacy returns here, without the status or the second recalculation.
        if ($amounts === null) {
            return $paymentId;
        }

        // :145-149 a settled invoice is paid (the calculator alone would not set it for a zero total).
        if ((float) $amounts->invoice_paid >= (float) $amounts->invoice_total) {
            DB::table('ip_invoices')->where('invoice_id', $invoiceId)->update(['invoice_status_id' => 4]);
        }

        $this->calculator->recalculateInvoice($invoiceId);

        return $paymentId;
    }
}
