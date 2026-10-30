<?php

use Tests\Support\InteractsWithLegacySession;
use Tests\Support\RestoresBaseline;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RestoresBaseline::class, InteractsWithLegacySession::class)
    ->in('Feature');

// The parity suite drives the two running apps over HTTP (see tests/Parity/Support).
pest()->extend(TestCase::class)
    ->beforeEach(function () {
        $missing = array_filter(['LEGACY_URL', 'NEW_URL', 'LEGACY_DB', 'NEW_DB'], fn (string $name) => ! env($name));

        if ($missing !== []) {
            $this->markTestSkipped(implode(', ', $missing).' unset; run `composer parity` with the compose stack up.');
        }
    })
    ->in('Parity');

require_once __DIR__.'/Parity/Support/parity.php';

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
