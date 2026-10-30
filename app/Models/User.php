<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * ip_users. The password lives in user_password as a 60-char bcrypt hash. Users without
 * user_psalt (pre-1.2.0 MD5 hashes, which legacy upgrades on login) are refused by
 * SessionController, see Mdl_sessions.php:40. The table has no remember-token
 * column, so the token name is empty and Laravel never reads or writes one.
 */
class User extends Authenticatable
{
    protected $table = 'ip_users';

    protected $primaryKey = 'user_id';

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['user_password', 'user_psalt', 'user_passwordreset_token'];

    public function getAuthPasswordName(): string
    {
        return 'user_password';
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'user_id');
    }
}
