<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $table = 'ip_units';

    protected $primaryKey = 'unit_id';

    public $timestamps = false;

    protected $guarded = [];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'unit_id');
    }
}
