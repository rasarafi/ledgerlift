<?php

namespace App\Support;

/**
 * CI_Security::xss_clean() of the CodeIgniter 3 fork InvoicePlane v1.7.2 runs on
 * (system/core/Security.php, MIT licence, Copyright (c) 2019 - 2022 CodeIgniter Foundation),
 * ported method for method so the same input is stored. It rewrites, never rejects.
 */
final class XssClean
{
    private const NEVER_ALLOWED = [
        'document.cookie' => '[removed]',
        '(document).cookie' => '[removed]',
        'document.write' => '[removed]',
        '(document).write' => '[removed]',
        '.parentNode' => '[removed]',
        '.innerHTML' => '[removed]',
        '-moz-binding' => '[removed]',
        '<!--' => '&lt;!--',
        '-->' => '--&gt;',
        '<![CDATA[' => '&lt;![CDATA[',
        '<comment>' => '&lt;comment&gt;',
        '<%' => '&lt;&#37;',
    ];

    private const NEVER_ALLOWED_REGEX = [
        'javascript\s*:',
        '(\(?document\)?|\(?window\)?(\.document)?)\.(location|on\w*)',
        'expression\s*(\(|&\#40;)',
        'vbscript\s*:',
        'wscript\s*:',
        'jscript\s*:',
        'vbs\s*:',
        'Redirect\s+30\d',
        "([\"'])?data\s*:[^\\1]*?base64[^\\1]*?,[^\\1]*?\\1?",
    ];

    private const NAUGHTY_TAGS = [
        'alert', 'area', 'prompt', 'confirm', 'applet', 'audio', 'basefont', 'base', 'behavior', 'bgsound',
        'blink', 'body', 'embed', 'expression', 'form', 'frameset', 'frame', 'head', 'html', 'ilayer',
        'iframe', 'input', 'button', 'select', 'isindex', 'layer', 'link', 'meta', 'keygen', 'object',
        'plaintext', 'style', 'script', 'textarea', 'title', 'math', 'video', 'svg', 'xml', 'xss',
    ];

    private const EVIL_ATTRIBUTES = ['on\w+', 'style', 'xmlns', 'formaction', 'form', 'xlink:href', 'FSCommand', 'seekSegmentTime'];

    private const COMPACT_WORDS = [
        'javascript', 'expression', 'vbscript', 'jscript', 'wscript',
        'vbs', 'script', 'base64', 'applet', 'alert', 'document',
        'write', 'cookie', 'window', 'confirm', 'prompt', 'eval',
    ];

    private const CALL_WORDS = 'alert|prompt|confirm|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink';

    private ?string $hash = null;

    /** CI_Security::xss_clean() of one string; FilterLegacyInput walks arrays. */
    public function clean(string $value): string
    {
        $str = $this->removeInvisibleCharacters($value);

        // Decode URL-encoded stuff, repeatedly (rawurldecode keeps plus signs).
        if (stripos($str, '%') !== false) {
            do {
                $old = $str;
                $str = rawurldecode($str);
                $str = (string) preg_replace_callback('#%(?:\s*[0-9a-f]){2,}#i', $this->urlDecodeSpaces(...), $str);
            } while ($old !== $str);
        }

        // Character entities to ASCII, only within tags and attributes.
        $str = (string) preg_replace_callback("/[^a-z0-9>]+[a-z0-9]+=(['\"]).*?\\1/si", $this->convertAttribute(...), $str);
        $str = (string) preg_replace_callback('/<\w+.*/si', $this->decodeEntity(...), $str);

        $str = $this->removeInvisibleCharacters($str);
        $str = str_replace("\t", ' ', $str);

        $str = $this->doNeverAllowed($str);
        $str = str_replace(['<?', '?>'], ['&lt;?', '?&gt;'], $str);

        // Compact exploded words: "j a v a s c r i p t".
        foreach (self::COMPACT_WORDS as $word) {
            $word = implode('\s*', str_split($word)).'\s*';
            $str = (string) preg_replace_callback('#('.substr($word, 0, -3).')(\W)#is', $this->compactExplodedWords(...), $str);
        }

        // Disallowed javascript in links and images.
        do {
            $original = $str;

            if (preg_match('/<a/i', $str)) {
                $str = (string) preg_replace_callback('#<a(?:rea)?[^a-z0-9>]+([^>]*?)(?:>|$)#si', $this->jsLinkRemoval(...), $str);
            }

            if (preg_match('/<img/i', $str)) {
                $str = (string) preg_replace_callback('#<img[^a-z0-9]+([^>]*?)(?:\s?/?>|$)#si', $this->jsImageRemoval(...), $str);
            }

            if (preg_match('/script|xss/i', $str)) {
                $str = (string) preg_replace('#</*(?:script|xss).*?>#si', '[removed]', $str);
            }
        } while ($original !== $str);

        // Naughty HTML elements become entities.
        $pattern = '#'
            .'<((?<slash>/*\s*)((?<tagName>[a-z0-9]+)(?=[^a-z0-9]|$)|.+)'
            .'[^\s\042\047a-z0-9>/=]*'
            .'(?<attributes>(?:[\s\042\047/=]*'
            .'[^\s\042\047>/=]+'
                .'(?:\s*='
                    .'(?:[^\s\042\047=><`]+|\s*\042[^\042]*\042|\s*\047[^\047]*\047|\s*(?U:[^\s\042\047=><`]*))'
                .')?'
            .')*)'
            .'[^>]*)(?<closeTag>\>)?#isS';

        do {
            $old = $str;
            $str = (string) preg_replace_callback($pattern, $this->sanitizeNaughtyHtml(...), $str);
        } while ($old !== $str);

        // Naughty scripting: eval('x') becomes eval&#40;'x'&#41;.
        $str = (string) preg_replace('#('.self::CALL_WORDS.')(\s*)\((.*?)\)#si', '\\1\\2&#40;\\3&#41;', $str);
        $str = (string) preg_replace('#('.self::CALL_WORDS.')(\s*)`(.*?)`#si', '\\1\\2&#96;\\3&#96;', $str);

        return $this->doNeverAllowed($str);
    }

    private function removeInvisibleCharacters(string $str): string
    {
        $patterns = [
            '/%0[0-8bcef]/i',
            '/%1[0-9a-f]/i',
            '/%7f/i',
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S',
        ];

        do {
            $str = (string) preg_replace($patterns, '', $str, -1, $count);
        } while ($count);

        return $str;
    }

    /** @param  array<int|string, string>  $matches */
    private function urlDecodeSpaces(array $matches): string
    {
        $noSpaces = (string) preg_replace('#\s+#', '', $matches[0]);

        return $noSpaces === $matches[0] ? $matches[0] : rawurldecode($noSpaces);
    }

    /** @param  array<int|string, string>  $matches */
    private function compactExplodedWords(array $matches): string
    {
        return preg_replace('/\s+/s', '', $matches[1]).$matches[2];
    }

    /** @param  array<int|string, string>  $match */
    private function convertAttribute(array $match): string
    {
        return str_replace(['>', '<', '\\'], ['&gt;', '&lt;', '\\\\'], $match[0]);
    }

    /** @param  array<int|string, string>  $match */
    private function decodeEntity(array $match): string
    {
        // Protect GET variables in URLs.
        $protected = (string) preg_replace('|\&([a-z\_0-9\-]+)\=([a-z\_0-9\-/]+)|i', $this->hash().'\\1=\\2', $match[0]);

        return str_replace($this->hash(), '&', $this->entityDecode($protected));
    }

    private function hash(): string
    {
        return $this->hash ??= md5((string) mt_rand());
    }

    /** CI_Security::entity_decode(): decodes entities even when the semicolon is missing. */
    private function entityDecode(string $str): string
    {
        if (! str_contains($str, '&')) {
            return $str;
        }

        static $entities = null;
        $entities ??= array_map('strtolower', get_html_translation_table(HTML_ENTITIES, ENT_COMPAT | ENT_HTML5, 'UTF-8'));

        do {
            $previous = $str;

            if (preg_match_all('/&[a-z]{2,}(?![a-z;])/i', $str, $found)) {
                $replace = [];

                foreach (array_unique(array_map('strtolower', $found[0])) as $entity) {
                    if (($char = array_search($entity.';', $entities, true)) !== false) {
                        $replace[$entity] = $char;
                    }
                }

                $str = str_replace(array_keys($replace), array_values($replace), $str);
            }

            $str = html_entity_decode(
                (string) preg_replace('/(&#(?:x0*[0-9a-f]{2,5}(?![0-9a-f;])|(?:0*\d{2,4}(?![0-9;]))))/iS', '$1;', $str),
                ENT_COMPAT | ENT_HTML5,
                'UTF-8',
            );
        } while ($previous !== $str);

        return $str;
    }

    /** @param  array<int|string, string>  $match */
    private function jsLinkRemoval(array $match): string
    {
        return str_replace(
            $match[1],
            (string) preg_replace(
                '#href=.*?(?:(?:alert|prompt|confirm)(?:\(|&\#40;|`|&\#96;)|javascript:|livescript:|mocha:|charset=|window\.|\(?document\)?\.|\.cookie|<script|<xss|d\s*a\s*t\s*a\s*:)#si',
                '',
                $this->filterAttributes($match[1]),
            ),
            $match[0],
        );
    }

    /** @param  array<int|string, string>  $match */
    private function jsImageRemoval(array $match): string
    {
        return str_replace(
            $match[1],
            (string) preg_replace(
                '#src=.*?(?:(?:alert|prompt|confirm|eval)(?:\(|&\#40;|`|&\#96;)|javascript:|livescript:|mocha:|charset=|window\.|\(?document\)?\.|\.cookie|<script|<xss|base64\s*,)#si',
                '',
                $this->filterAttributes($match[1]),
            ),
            $match[0],
        );
    }

    private function filterAttributes(string $str): string
    {
        $out = '';

        if (preg_match_all('#\s*[a-z\-]+\s*=\s*(\042|\047)([^\\1]*?)\\1#is', $str, $matches)) {
            foreach ($matches[0] as $match) {
                $out .= preg_replace('#/\*.*?\*/#s', '', $match);
            }
        }

        return $out;
    }

    /** @param  array<int|string, string>  $matches */
    private function sanitizeNaughtyHtml(array $matches): string
    {
        if (empty($matches['closeTag'])) {
            return '&lt;'.$matches[1];
        }

        if (in_array(strtolower($matches['tagName']), self::NAUGHTY_TAGS, true)) {
            return '&lt;'.$matches[1].'&gt;';
        }

        if (! isset($matches['attributes'])) {
            return $matches[0];
        }

        $attributes = [];
        $attributesPattern = '#'
            .'(?<name>[^\s\042\047>/=]+)'
            .'(?:\s*=(?<value>[^\s\042\047=><`]+|\s*\042[^\042]*\042|\s*\047[^\047]*\047|\s*(?U:[^\s\042\047=><`]*)))'
            .'#i';
        $isEvilPattern = '#^('.implode('|', self::EVIL_ATTRIBUTES).')$#i';
        $remaining = $matches['attributes'];

        // Each iteration filters a single attribute.
        do {
            $remaining = (string) preg_replace('#^[^a-z]+#i', '', $remaining);

            if (! preg_match($attributesPattern, $remaining, $attribute, PREG_OFFSET_CAPTURE)) {
                break;
            }

            $attributes[] = preg_match($isEvilPattern, $attribute['name'][0]) || trim($attribute['value'][0]) === ''
                ? 'xss=removed'
                : $attribute[0][0];

            $remaining = substr($remaining, $attribute[0][1] + strlen($attribute[0][0]));
        } while ($remaining !== '');

        return '<'.$matches['slash'].$matches['tagName'].($attributes === [] ? '' : ' '.implode(' ', $attributes)).'>';
    }

    private function doNeverAllowed(string $str): string
    {
        $str = str_replace(array_keys(self::NEVER_ALLOWED), self::NEVER_ALLOWED, $str);

        foreach (self::NEVER_ALLOWED_REGEX as $regex) {
            $str = (string) preg_replace('#'.$regex.'#is', '[removed]', $str);
        }

        return $str;
    }
}
