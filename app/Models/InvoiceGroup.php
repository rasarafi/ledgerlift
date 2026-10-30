<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceGroup extends Model
{
    protected $table = 'ip_invoice_groups';

    protected $primaryKey = 'invoice_group_id';

    public $timestamps = false;

    protected $guarded = [];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'invoice_group_id');
    }
}
