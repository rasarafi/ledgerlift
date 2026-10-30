<?php

use App\Providers\AppServiceProvider;
use Illuminate\Database\Console\Migrations\FreshCommand;

/**
 * Boots the provider against the given database name and reports whether
 * `migrate:fresh` (and so the whole destructive family) is prohibited.
 */
function freshIsProhibitedFor(string $database): bool
{
    config(['database.connections.'.config('database.default').'.database' => $database]);

    (new AppServiceProvider(app()))->boot();

    return (new ReflectionProperty(FreshCommand::class, 'prohibitedFromRunning'))->getValue();
}

afterEach(function () {
    // Leave the guard in the state the real database config implies.
    (new AppServiceProvider(app()))->boot();
});

it('prohibits migrate:fresh against a non-test database', function () {
    expect(freshIsProhibitedFor('ledgerlift_dev'))->toBeTrue();
});

it('allows migrate:fresh against a _test database', function () {
    expect(freshIsProhibitedFor('ledgerlift_test'))->toBeFalse();
});

it('keys on the database name, not the environment', function () {
    expect(app()->environment())->toBe('testing');
    expect(freshIsProhibitedFor('ledgerlift_dev'))->toBeTrue();
});
