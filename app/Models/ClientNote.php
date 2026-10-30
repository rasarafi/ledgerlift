<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientNote extends Model
{
    protected $table = 'ip_client_notes';

    protected $primaryKey = 'client_note_id';

    public $timestamps = false;

    protected $guarded = [];
}
