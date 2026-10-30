<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $table = 'ip_clients';

    protected $primaryKey = 'client_id';

    public $timestamps = false;

    protected $guarded = [];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'client_id');
    }
}
