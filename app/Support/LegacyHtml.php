<?php

namespace App\Support;

/**
 * Two url_helper.php functions of the CodeIgniter fork legacy runs on (system/helpers/url_helper.php,
 * MIT licence, Copyright (c) 2019 - 2022 CodeIgniter Foundation), ported so a client's e-mail and
 * web address render as legacy renders them. Both work on text that is already escaped.
 */
final class LegacyHtml
{
    /** auto_link(): `$type` is 'email', 'url' or 'both'. */
    public static function autoLink(string $str, string $type = 'both', bool $popup = false): string
    {
        if ($type !== 'email' && preg_match_all('#(\w*://|www\.)[a-z0-9]+(-+[a-z0-9]+)*(\.[a-z0-9]+(-+[a-z0-9]+)*)+(/([^\s()<>;]+\w)?/?)?#i', $str, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            $target = $popup ? ' target="_blank" rel="noopener"' : '';

            // Last to first, so the offsets of the earlier matches stay valid.
            foreach (array_reverse($matches) as $match) {
                $link = '<a href="'.(strpos($match[1][0], '/') ? '' : 'http://').$match[0][0].'"'.$target.'>'.$match[0][0].'</a>';
                $str = substr_replace($str, $link, $match[0][1], strlen($match[0][0]));
            }
        }

        if ($type !== 'url' && preg_match_all('#([\w\.\-\+]+@[a-z0-9\-]+\.[a-z0-9\-\.]+[^[:punct:]\s])#i', $str, $matches, PREG_OFFSET_CAPTURE)) {
            foreach (array_reverse($matches[0]) as $match) {
                if (filter_var($match[0], FILTER_VALIDATE_EMAIL) !== false) {
                    $str = substr_replace($str, self::safeMailto($match[0]), $match[1], strlen($match[0]));
                }
            }
        }

        return $str;
    }

    /** safe_mailto(): the link written by a script from character codes, so the address is not in the HTML. */
    public static function safeMailto(string $email): string
    {
        // Only addresses FILTER_VALIDATE_EMAIL accepts get here, which are ASCII: the title (the
        // address itself) needs none of the multi-byte handling safe_mailto() has.
        $codes = array_map(fn (string $char) => '|'.ord($char), str_split($email));
        $x = [...str_split('<a href="mailto:'), ...$codes, '"', '>', ...$codes];

        array_push($x, '<', '/', 'a', '>');

        $output = "<script type=\"text/javascript\">\n\t//<![CDATA[\n\tvar l=new Array();\n";

        foreach (array_reverse($x) as $i => $part) {
            $output .= "\tl[".$i."] = '".$part."';\n";
        }

        return $output."\n\tfor (var i = l.length-1; i >= 0; i=i-1) {\n"
            ."\t\tif (l[i].substring(0, 1) === '|') document.write(\"&#\"+unescape(l[i].substring(1))+\";\");\n"
            ."\t\telse document.write(unescape(l[i]));\n"
            ."\t}\n"
            ."\t//]]>\n"
            .'</script>';
    }
}
