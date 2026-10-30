<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Family extends Model
{
    protected $table = 'ip_families';

    protected $primaryKey = 'family_id';

    public $timestamps = false;

    protected $guarded = [];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'family_id');
    }
}
