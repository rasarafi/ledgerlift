<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $table = 'ip_payments';

    protected $primaryKey = 'payment_id';

    public $timestamps = false;

    protected $guarded = [];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /**
     * Mdl_payments::default_select(), default_join() and default_order_by(), with explicit
     * columns instead of legacy's overlapping `x.*`. Every join but the payment method's is inner.
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeListing(Builder $query): void
    {
        $query->join('ip_invoices', 'ip_invoices.invoice_id', '=', 'ip_payments.invoice_id')
            ->join('ip_clients', 'ip_clients.client_id', '=', 'ip_invoices.client_id')
            ->join('ip_invoice_amounts', 'ip_invoice_amounts.invoice_id', '=', 'ip_invoices.invoice_id')
            ->leftJoin('ip_payment_methods', 'ip_payment_methods.payment_method_id', '=', 'ip_payments.payment_method_id')
            ->select(
                'ip_payments.*', 'ip_payment_methods.payment_method_name',
                'ip_clients.client_id', 'ip_clients.client_title', 'ip_clients.client_name', 'ip_clients.client_surname',
                'ip_invoices.invoice_number', 'ip_invoices.invoice_date_created',
            )
            ->orderByRaw('ip_payments.payment_date DESC, ip_payments.payment_id DESC');
    }
}
