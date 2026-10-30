<?php

use App\Support\LegacyLike;

it('escapes the characters CI treats specially in a LIKE clause with !', function (string $input, string $escaped) {
    expect(LegacyLike::escape($input))->toBe($escaped);
})->with([
    'plain' => ['host', 'host'],
    'percent' => ['100%', '100!%'],
    'underscore' => ['web_design', 'web!_design'],
    'bang first' => ['!%_', '!!!%!_'],
    'backslash and quote are left to the binding' => ["a\\b'c", "a\\b'c"],
    'empty' => ['', ''],
]);
