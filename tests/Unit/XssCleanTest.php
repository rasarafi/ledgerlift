<?php

use App\Support\XssClean;

/*
 * Each expectation is what CI_Security::xss_clean() of the legacy app's CodeIgniter returns
 * for the input (captured by running that class over the cases).
 */
it('cleans like CI_Security::xss_clean', function (string $input, string $expected) {
    expect((new XssClean)->clean($input))->toBe($expected);
})->with(require __DIR__.'/xss-cases.php');
