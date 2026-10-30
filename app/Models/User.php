<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * ip_users. The password lives in user_password (60-char bcrypt, or the legacy
 * salted format that Task 6 upgrades on login). The table has no remember-token
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
