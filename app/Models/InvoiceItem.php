<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InvoiceItem extends Model
{
    protected $table = 'ip_invoice_items';

    protected $primaryKey = 'item_id';

    public $timestamps = false;

    protected $guarded = [];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function amount(): HasOne
    {
        return $this->hasOne(InvoiceItemAmount::class, 'item_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'item_tax_rate_id');
    }
}
