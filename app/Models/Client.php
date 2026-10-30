<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Client extends Model
{
    protected $table = 'ip_clients';

    protected $primaryKey = 'client_id';

    public $timestamps = false;

    protected $guarded = [];

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'client_id');
    }

    /**
     * Mdl_clients::default_select() and default_order_by(): the row with its full name, by
     * surname or by name as `sort_clients_by_surname` says.
     *
     * @param  Builder<Client>  $query
     */
    public function scopeListing(Builder $query): void
    {
        $query->select('ip_clients.*', DB::raw('CONCAT(ip_clients.client_name, " ", ip_clients.client_surname) as client_fullname'))
            ->orderBy(get_setting('sort_clients_by_surname') == '1' ? 'ip_clients.client_surname' : 'ip_clients.client_name');
    }

    /**
     * with_total_balance() (always), with_total() and with_total_paid(): the sums over the client's invoices.
     *
     * @param  Builder<Client>  $query
     */
    public function scopeWithInvoiceTotals(Builder $query, bool $total = false, bool $paid = false): void
    {
        if ($total) {
            $query->selectRaw(self::invoiceSum('invoice_total', 'client_invoice_total'));
        }

        $query->selectRaw(self::invoiceSum('invoice_balance', 'client_invoice_balance'));

        if ($paid) {
            $query->selectRaw(self::invoiceSum('invoice_paid', 'client_invoice_paid'));
        }
    }

    /**
     * @param  literal-string  $column
     * @param  literal-string  $alias
     * @return literal-string
     */
    private static function invoiceSum(string $column, string $alias): string
    {
        return "IFnull((SELECT SUM($column) FROM ip_invoice_amounts WHERE invoice_id IN (SELECT invoice_id FROM ip_invoices WHERE ip_invoices.client_id = ip_clients.client_id)), 0) AS $alias";
    }
}
