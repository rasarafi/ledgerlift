<?php

use App\Support\LegacyHtml;

it('links a web address, with a popup target when asked', function () {
    expect(LegacyHtml::autoLink('see www.example.test/about now', 'url'))
        ->toBe('see <a href="http://www.example.test/about">www.example.test/about</a> now')
        ->and(LegacyHtml::autoLink('https://example.test', 'url', true))
        ->toBe('<a href="https://example.test" target="_blank" rel="noopener">https://example.test</a>')
        ->and(LegacyHtml::autoLink('plain text', 'url'))->toBe('plain text');
});

it('writes an e-mail address as a script of character codes, and leaves other text alone', function () {
    $linked = LegacyHtml::autoLink('a@b.test', 'email');

    expect($linked)->toStartWith("<script type=\"text/javascript\">\n\t//<![CDATA[\n\tvar l=new Array();\n\tl[0] = '>';")
        ->toContain("l[1] = 'a';", "l[4] = '|116';")
        ->not->toContain('a@b.test')
        ->and(LegacyHtml::autoLink('not an address', 'email'))->toBe('not an address')
        ->and(LegacyHtml::autoLink('www.example.test', 'email'))->toBe('www.example.test');
});
