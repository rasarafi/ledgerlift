<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    protected $table = 'ip_tax_rates';

    protected $primaryKey = 'tax_rate_id';

    public $timestamps = false;

    protected $guarded = [];
}
