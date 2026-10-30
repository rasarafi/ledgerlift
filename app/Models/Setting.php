<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'ip_settings';

    protected $primaryKey = 'setting_id';

    public $timestamps = false;

    protected $guarded = [];
}
