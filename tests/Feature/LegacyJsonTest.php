<?php

use App\Http\Responses\LegacyJson;

it('sends JSON as text/html, because json_parse in scripts.js calls data.search()', function () {
    $response = LegacyJson::make(['success' => 1, 'invoice_id' => 7]);

    expect($response->getContent())->toBe('{"success":1,"invoice_id":7}')
        ->and($response->headers->get('Content-Type'))->toBe('text/html; charset=UTF-8')
        ->and($response->getStatusCode())->toBe(200);
});

it('does not escape slashes or unicode differently from json_encode', function () {
    expect(LegacyJson::make(['a' => 'x/y', 'b' => 'é'])->getContent())->toBe(json_encode(['a' => 'x/y', 'b' => 'é']));
});
