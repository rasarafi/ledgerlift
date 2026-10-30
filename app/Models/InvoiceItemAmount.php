<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItemAmount extends Model
{
    protected $table = 'ip_invoice_item_amounts';

    protected $primaryKey = 'item_amount_id';

    public $timestamps = false;

    protected $guarded = [];

    /** @return BelongsTo<InvoiceItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class, 'item_id');
    }
}
