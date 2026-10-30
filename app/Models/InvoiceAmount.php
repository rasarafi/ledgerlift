<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class InvoiceAmount extends Model
{
    protected $table = 'ip_invoice_amounts';

    protected $primaryKey = 'invoice_amount_id';

    public $timestamps = false;

    protected $guarded = [];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * Mdl_invoice_amounts::get_status_totals() (:366-441): for every status, what the invoices created
     * in `$period` add up to (paid invoices: what was paid, the others: the balance) and how many there are.
     * The clock is MySQL's NOW(), as in legacy. The "last" periods shift NOW() but keep comparing with
     * the year of NOW(), so in January "last month" is December of this year, exactly as in legacy.
     * A period it does not know (or none) is this month.
     *
     * @return array<int, array{invoice_status_id: int, class: string, label: string, href: string, sum_total: string|int|null, num_total: int}>
     */
    public static function statusTotals(string $period): array
    {
        $created = 'ip_invoices.invoice_date_created';
        $condition = match ($period) {
            'last-month' => "MONTH($created) = MONTH(NOW() - INTERVAL 1 MONTH) AND YEAR($created) = YEAR(NOW())",
            'this-quarter' => "QUARTER($created) = QUARTER(NOW()) AND YEAR($created) = YEAR(NOW())",
            'last-quarter' => "QUARTER($created) = QUARTER(NOW() - INTERVAL 1 QUARTER) AND YEAR($created) = YEAR(NOW())",
            'this-year' => "YEAR($created) = YEAR(NOW())",
            'last-year' => "YEAR($created) = YEAR(NOW() - INTERVAL 1 YEAR)",
            default => "MONTH($created) = MONTH(NOW()) AND YEAR($created) = YEAR(NOW())",
        };

        $results = DB::select("
            SELECT ip_invoices.invoice_status_id, (CASE ip_invoices.invoice_status_id WHEN 4 THEN SUM(ip_invoice_amounts.invoice_paid) ELSE SUM(ip_invoice_amounts.invoice_balance) END) AS sum_total, COUNT(*) AS num_total
            FROM ip_invoice_amounts
            JOIN ip_invoices ON ip_invoices.invoice_id = ip_invoice_amounts.invoice_id AND $condition
            GROUP BY ip_invoices.invoice_status_id");

        $totals = [];

        foreach (Invoice::statuses() as $id => $status) {
            $totals[$id] = ['invoice_status_id' => $id] + $status + ['sum_total' => 0, 'num_total' => 0];
        }

        foreach ($results as $result) {
            // An invoice with a status outside 1-4 makes legacy fail; it is left out here.
            if (isset($totals[$result->invoice_status_id])) {
                $totals[$result->invoice_status_id]['sum_total'] = $result->sum_total;
                $totals[$result->invoice_status_id]['num_total'] = (int) $result->num_total;
            }
        }

        return $totals;
    }
}
