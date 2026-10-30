<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * Columns the listing scope computes:
 *
 * @property-read string|null $invoice_sign
 * @property-read string $invoice_total
 * @property-read string $invoice_balance
 * @property-read int $is_overdue
 * @property-read int $invoice_is_recurring
 */
class Invoice extends Model
{
    protected $table = 'ip_invoices';

    protected $primaryKey = 'invoice_id';

    public $timestamps = false;

    protected $guarded = [];

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<InvoiceGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(InvoiceGroup::class, 'invoice_group_id');
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    /** @return HasOne<InvoiceAmount, $this> */
    public function amount(): HasOne
    {
        return $this->hasOne(InvoiceAmount::class, 'invoice_id');
    }

    /** @return HasMany<InvoiceTaxRate, $this> */
    public function taxRates(): HasMany
    {
        return $this->hasMany(InvoiceTaxRate::class, 'invoice_id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }

    /**
     * Mdl_invoices::statuses(): label, CSS class and list link by status id.
     *
     * @return array<int, array{label: string, class: string, href: string}>
     */
    public static function statuses(): array
    {
        return [
            1 => ['label' => __('ip.draft'), 'class' => 'draft', 'href' => 'invoices/status/draft'],
            2 => ['label' => __('ip.sent'), 'class' => 'sent', 'href' => 'invoices/status/sent'],
            3 => ['label' => __('ip.viewed'), 'class' => 'viewed', 'href' => 'invoices/status/viewed'],
            4 => ['label' => __('ip.paid'), 'class' => 'paid', 'href' => 'invoices/status/paid'],
        ];
    }

    /**
     * Mdl_invoices::default_select(), default_join() and default_order_by(), with explicit
     * columns: legacy selects `x.*` from several tables and lets the later ones overwrite the
     * earlier. The joins to the client and the user are inner (an invoice without either is not
     * listed), the ones to the amounts, the sumex row and the quotes are left: an invoice that
     * two quotes were converted into is listed twice, and counted twice by the pager
     * (Mdl_invoices.php:134-141).
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeListing(Builder $query): void
    {
        $query->join('ip_clients', 'ip_clients.client_id', '=', 'ip_invoices.client_id')
            ->join('ip_users', 'ip_users.user_id', '=', 'ip_invoices.user_id')
            ->leftJoin('ip_invoice_amounts', 'ip_invoice_amounts.invoice_id', '=', 'ip_invoices.invoice_id')
            ->leftJoin('ip_invoice_sumex', 'ip_invoice_sumex.sumex_invoice', '=', 'ip_invoices.invoice_id')
            ->leftJoin('ip_quotes', 'ip_quotes.invoice_id', '=', 'ip_invoices.invoice_id')
            ->select(
                'ip_invoices.invoice_id', 'ip_invoices.client_id', 'ip_invoices.user_id', 'ip_invoices.invoice_status_id', 'ip_invoices.is_read_only',
                'ip_invoices.invoice_number', 'ip_invoices.invoice_date_created', 'ip_invoices.invoice_date_due', 'ip_invoices.payment_method',
                'ip_clients.client_title', 'ip_clients.client_name', 'ip_clients.client_surname',
                'ip_invoice_amounts.invoice_sign AS invoice_sign',
                DB::raw("IFnull(ip_invoice_amounts.invoice_total, '0.00') AS invoice_total"),
                DB::raw("IFnull(ip_invoice_amounts.invoice_balance, '0.00') AS invoice_balance"),
                DB::raw('(CASE WHEN ip_invoices.invoice_status_id NOT IN (1,4) AND DATEDIFF(NOW(), invoice_date_due) > 0 THEN 1 ELSE 0 END) is_overdue'),
                DB::raw('(CASE (SELECT COUNT(*) FROM ip_invoices_recurring WHERE ip_invoices_recurring.invoice_id = ip_invoices.invoice_id and ip_invoices_recurring.recur_next_date IS NOT NULL) WHEN 0 THEN 0 ELSE 1 END) AS invoice_is_recurring'),
            )
            ->orderByRaw('ip_invoices.invoice_date_created DESC, ip_invoices.invoice_number DESC, ip_invoices.invoice_id DESC');
    }

    /**
     * The listing with everything Invoices::view() reads from Mdl_invoices::get_by_id(): every
     * invoice column, the client's, the user's name and the remaining amounts.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeDetail(Builder $query): void
    {
        $query->listing()->addSelect(
            'ip_clients.*', 'ip_users.user_name', 'ip_invoice_sumex.sumex_id',
            DB::raw("IFnull(ip_invoice_amounts.invoice_item_subtotal, '0.00') AS invoice_item_subtotal"),
            DB::raw("IFnull(ip_invoice_amounts.invoice_item_tax_total, '0.00') AS invoice_item_tax_total"),
            DB::raw("IFnull(ip_invoice_amounts.invoice_tax_total, '0.00') AS invoice_tax_total"),
            DB::raw("IFnull(ip_invoice_amounts.invoice_paid, '0.00') AS invoice_paid"),
            'ip_invoices.*',
        );
    }
}
