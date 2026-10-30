<?php

use Illuminate\Support\Facades\DB;

// The two isolation tests rely on Pest running a file's tests in declaration order.

it('writes to the database (isolation, part 1)', function () {
    DB::table('ip_clients')->insert([
        'client_date_created' => now(),
        'client_date_modified' => now(),
        'client_name' => 'Isolation Probe',
    ]);

    expect(DB::table('ip_clients')->count())->toBe(1);
});

it('starts from the baseline again (isolation, part 2)', function () {
    expect(DB::table('ip_clients')->count())->toBe(0);
});

it('has the admin user with a bcrypt password', function () {
    $admin = DB::table('ip_users')->where('user_email', 'admin@ledgerlift.test')->first();

    expect($admin)->not->toBeNull()
        ->and(password_verify('ledgerlift-demo-1', $admin->user_password))->toBeTrue()
        ->and($admin->user_type)->toBe(1)
        ->and($admin->user_active)->toBe(1)
        ->and(strlen($admin->user_psalt))->toBe(22);
});
