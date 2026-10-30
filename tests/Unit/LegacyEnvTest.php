<?php

use App\Support\LegacyEnv;

/*
 * index.php env_bool(): `env($key, 'false') === 'true'`, so only the exact string `true` turns a
 * flag on. Laravel's env() would also read "TRUE" and "(true)" as true.
 */

function legacyEnvWith(string $key, ?string $value, Closure $read): mixed
{
    $before = $_SERVER[$key] ?? null;

    if ($value === null) {
        unset($_SERVER[$key]);
    } else {
        $_SERVER[$key] = $value;
    }

    try {
        return $read();
    } finally {
        if ($before === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $before;
        }
    }
}

it('reads only the exact string true as true', function (?string $value, bool $expected) {
    expect(legacyEnvWith('LEDGERLIFT_TEST_FLAG', $value, fn () => LegacyEnv::bool('LEDGERLIFT_TEST_FLAG')))->toBe($expected);
})->with([
    'true' => ['true', true],
    '1' => ['1', false],
    'yes' => ['yes', false],
    'TRUE' => ['TRUE', false],
    '(true)' => ['(true)', false],
    'false' => ['false', false],
    'empty' => ['', false],
    'unset' => [null, false],
]);

it('parses ENABLE_INVOICE_DELETION and DISABLE_READ_ONLY in the config like env_bool()', function (string $value, bool $expected) {
    $config = legacyEnvWith('ENABLE_INVOICE_DELETION', $value, fn () => legacyEnvWith('DISABLE_READ_ONLY', $value, fn () => require dirname(__DIR__, 2).'/config/ledgerlift.php'));

    expect($config['enable_invoice_deletion'])->toBe($expected)
        ->and($config['disable_read_only'])->toBe($expected);
})->with([
    ['true', true],
    ['1', false],
    ['yes', false],
    ['TRUE', false],
]);
