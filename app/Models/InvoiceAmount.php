<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
